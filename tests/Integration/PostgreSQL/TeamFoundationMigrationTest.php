<?php

use App\CampaignInvitationStatus;
use App\ExamSessionStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return array{connection: string, schema: string}
 */
function createTeamFoundationPostgresSchema(): array
{
    $baseConnection = config('database.connections.pgsql');
    $integrationDatabase = env('POSTGRES_INTEGRATION_DATABASE');

    if (filled($integrationDatabase)) {
        $baseConnection['database'] = $integrationDatabase;
    }

    config(['database.connections.pgsql' => $baseConnection]);
    DB::purge('pgsql');

    try {
        $postgres = DB::connection('pgsql');
        $postgres->getPdo();
    } catch (Throwable $exception) {
        if (filled($integrationDatabase)) {
            throw $exception;
        }

        test()->markTestSkipped('PostgreSQL is unavailable: '.$exception->getMessage());
    }

    $schema = 'team_foundation_test_'.Str::lower(Str::random(12));
    $connection = 'team_foundation_postgres';

    $postgres->statement("CREATE SCHEMA {$schema}");

    config([
        "database.connections.{$connection}" => [
            ...$baseConnection,
            'search_path' => $schema,
        ],
    ]);
    DB::purge($connection);

    return compact('connection', 'schema');
}

function dropTeamFoundationPostgresSchema(string $connection, string $schema): void
{
    DB::purge($connection);
    DB::connection('pgsql')->statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
}

/**
 * @return list<string>
 */
function teamFoundationMigrationPaths(bool $legacy): array
{
    $paths = glob(database_path('migrations/*.php')) ?: [];

    return array_values(array_filter(
        $paths,
        fn (string $path): bool => $legacy
            ? basename($path) < '2026_07_10_120340_create_team_foundation_tables.php'
            : basename($path) >= '2026_07_10_120340_create_team_foundation_tables.php',
    ));
}

/** @param list<string> $paths */
function migrateTeamFoundationPaths(string $connection, array $paths): void
{
    Artisan::call('migrate', [
        '--database' => $connection,
        '--path' => $paths,
        '--realpath' => true,
        '--force' => true,
        '--no-interaction' => true,
    ]);
}

/**
 * @return array{administrator: int, candidates: list<int>, campaigns: list<int>, sessions: list<int>}
 */
function seedValidLegacyTeamData(string $connection): array
{
    $database = DB::connection($connection);
    $now = now();
    $administrator = $database->table('users')->insertGetId([
        'name' => 'Legacy Administrator',
        'email' => 'administrator@example.com',
        'role' => 'admin',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $candidates = [];

    foreach (range(1, 3) as $number) {
        $candidates[] = $database->table('users')->insertGetId([
            'name' => "Candidate {$number}",
            'email' => "candidate{$number}@example.com",
            'role' => 'candidate',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $campaigns = [];

    foreach (range(1, 4) as $number) {
        $campaigns[] = $database->table('campaigns')->insertGetId([
            'created_by' => $administrator,
            'title' => "Legacy Campaign {$number}",
            'role_title' => "Role {$number}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    foreach ([0, 1] as $index) {
        $database->table('campaign_invitations')->insert([
            'campaign_id' => $campaigns[$index],
            'email' => 'candidate'.($index + 1).'@example.com',
            'user_id' => $candidates[$index],
            'token_hash' => hash('sha256', "legacy-token-{$index}"),
            'invited_by' => $administrator,
            'accepted_at' => $now,
            'status' => CampaignInvitationStatus::Accepted->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $assessment = $database->table('assessments')->insertGetId([
        'user_id' => $candidates[2],
        'campaign_id' => $campaigns[2],
        'answers_payload' => json_encode(['answer' => 'preserved'], JSON_THROW_ON_ERROR),
        'status' => 'submitted',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $sessions = [];

    foreach ([0, 1] as $index) {
        $sessions[] = $database->table('exam_sessions')->insertGetId([
            'user_id' => $candidates[$index],
            'campaign_id' => $campaigns[$index],
            'assessment_id' => null,
            'status' => ExamSessionStatus::InProgress->value,
            'current_section_started_at' => $now,
            'answer_drafts' => json_encode(['draft' => "answer {$index}"], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    expect($assessment)->toBeInt();

    return compact('administrator', 'candidates', 'campaigns', 'sessions');
}

test('postgresql migrates production-shaped legacy data and enforces team constraints', function () {
    ['connection' => $connection, 'schema' => $schema] = createTeamFoundationPostgresSchema();

    try {
        migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: true));
        $legacy = seedValidLegacyTeamData($connection);
        migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: false));

        $database = DB::connection($connection);
        $team = $database->table('teams')->sole();
        $owner = $database->table('team_memberships')->where('role', 'owner')->whereNull('ended_at')->sole();
        $operator = $database->table('platform_operator_authorities')->whereNull('revoked_at')->sole();
        $administrator = $database->table('users')->where('id', $legacy['administrator'])->sole();
        $campaigns = $database->table('campaigns')->orderBy('id')->get();
        $sessions = $database->table('exam_sessions')->orderBy('id')->get();
        $invitations = $database->table('campaign_invitations')->orderBy('id')->get();
        $assessment = $database->table('assessments')->sole();
        $activity = $database->table('team_activities')->sole();

        expect($team->name)->toBe("Legacy Administrator's Team")
            ->and($team->status)->toBe('active')
            ->and($owner->user_id)->toBe($legacy['administrator'])
            ->and($operator->user_id)->toBe($legacy['administrator'])
            ->and($administrator->current_team_id)->toBe($team->id)
            ->and($database->getSchemaBuilder()->hasColumn('users', 'role'))->toBeFalse()
            ->and($campaigns)->toHaveCount(4)
            ->and($campaigns->pluck('team_id')->unique()->all())->toBe([$team->id])
            ->and($campaigns->pluck('created_by')->unique()->all())->toBe([$legacy['administrator']])
            ->and($invitations->pluck('user_id')->all())->toBe([$legacy['candidates'][0], $legacy['candidates'][1]])
            ->and($invitations->pluck('campaign_id')->all())->toBe([$legacy['campaigns'][0], $legacy['campaigns'][1]])
            ->and($assessment->campaign_id)->toBe($legacy['campaigns'][2])
            ->and($assessment->user_id)->toBe($legacy['candidates'][2])
            ->and(json_decode($assessment->answers_payload, true, flags: JSON_THROW_ON_ERROR))->toBe(['answer' => 'preserved'])
            ->and($sessions)->toHaveCount(2)
            ->and($sessions->pluck('status')->unique()->all())->toBe([ExamSessionStatus::InProgress->value])
            ->and($sessions->pluck('id')->all())->toBe($legacy['sessions'])
            ->and($sessions->pluck('campaign_id')->all())->toBe([$legacy['campaigns'][0], $legacy['campaigns'][1]])
            ->and($sessions->pluck('user_id')->all())->toBe([$legacy['candidates'][0], $legacy['candidates'][1]])
            ->and(json_decode($sessions[0]->answer_drafts, true, flags: JSON_THROW_ON_ERROR))->toBe(['draft' => 'answer 0'])
            ->and($activity->after_state)->not->toContain('candidate@example.com')
            ->not->toContain('legacy-token');

        foreach ([$team->name, mb_strtoupper($team->name)] as $index => $name) {
            $database->transaction(function () use ($database, $legacy, $index, $name): void {
                $duplicateNameTeam = $database->table('teams')->insertGetId([
                    'name' => $name,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $database->table('team_memberships')->insert([
                    'team_id' => $duplicateNameTeam,
                    'user_id' => $legacy['candidates'][$index],
                    'role' => 'owner',
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }

        expect(fn () => $database->table('team_memberships')->insert([
            'team_id' => $team->id,
            'user_id' => $legacy['administrator'],
            'role' => 'collaborator',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('team_memberships')->insert([
                'team_id' => $team->id,
                'user_id' => $legacy['candidates'][0],
                'role' => 'owner',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('team_memberships')->insert([
                'team_id' => $team->id,
                'user_id' => $legacy['candidates'][0],
                'role' => 'Owner',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('team_memberships')->where('id', $owner->id)->update([
                'ended_at' => now(),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('teams')->insert([
                'name' => 'Ownerless Team',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('campaigns')->where('id', $legacy['campaigns'][0])->update([
                'team_id' => $database->table('teams')->where('name', mb_strtoupper($team->name))->value('id'),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('campaigns')->insert([
                'team_id' => null,
                'created_by' => $legacy['administrator'],
                'title' => 'Unowned Campaign',
                'role_title' => 'Role',
                'created_at' => now(),
                'updated_at' => now(),
            ]))->toThrow(QueryException::class)
            ->and(fn () => $database->table('team_activities')->where('id', $activity->id)->update([
                'action' => 'changed',
            ]))->toThrow(QueryException::class);
    } finally {
        dropTeamFoundationPostgresSchema($connection, $schema);
    }
});

test('postgresql backfill fails before assigning campaigns with invalid legacy ownership', function () {
    ['connection' => $connection, 'schema' => $schema] = createTeamFoundationPostgresSchema();

    try {
        migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: true));
        $database = DB::connection($connection);
        $now = now();

        $administrator = $database->table('users')->insertGetId([
            'name' => 'Legacy Administrator',
            'email' => 'administrator@example.com',
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $campaign = null;

        foreach (range(1, 4) as $number) {
            $campaignId = $database->table('campaigns')->insertGetId([
                'created_by' => $number === 1 ? null : $administrator,
                'title' => "Legacy Campaign {$number}",
                'role_title' => 'Role',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $campaign ??= $campaignId;
        }

        expect(fn () => migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: false)))
            ->toThrow(RuntimeException::class, 'not owned by the legacy administrator')
            ->and($database->table('teams')->count())->toBe(0)
            ->and($database->table('campaigns')->where('id', $campaign)->value('team_id'))->toBeNull()
            ->and($database->table('users')->where('id', $administrator)->value('current_team_id'))->toBeNull();
    } finally {
        dropTeamFoundationPostgresSchema($connection, $schema);
    }
});

test('postgresql backfill fails when the legacy campaign count is not four', function () {
    ['connection' => $connection, 'schema' => $schema] = createTeamFoundationPostgresSchema();

    try {
        migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: true));
        $database = DB::connection($connection);
        $now = now();
        $administrator = $database->table('users')->insertGetId([
            'name' => 'Legacy Administrator',
            'email' => 'administrator@example.com',
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(1, 3) as $number) {
            $database->table('campaigns')->insert([
                'created_by' => $administrator,
                'title' => "Legacy Campaign {$number}",
                'role_title' => 'Role',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        expect(fn () => migrateTeamFoundationPaths($connection, teamFoundationMigrationPaths(legacy: false)))
            ->toThrow(RuntimeException::class, 'exactly four legacy Campaigns')
            ->and($database->table('teams')->count())->toBe(0)
            ->and($database->table('campaigns')->whereNull('team_id')->count())->toBe(3);
    } finally {
        dropTeamFoundationPostgresSchema($connection, $schema);
    }
});
