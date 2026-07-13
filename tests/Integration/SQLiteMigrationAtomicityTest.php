<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('sqlite rolls back campaign constraint rebuild when trigger creation fails', function () {
    $connection = 'migration_atomicity_sqlite';
    config([
        "database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge($connection);

    $migrationPaths = array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        fn (string $path): bool => basename($path) <= '2026_07_10_120347_enforce_team_foundation_constraints.php',
    ));

    Artisan::call('migrate', [
        '--database' => $connection,
        '--path' => $migrationPaths,
        '--realpath' => true,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $previousDefault = config('database.default');
    config(['database.default' => $connection]);
    /** @var object{up: callable, down: callable} $migration */
    $migration = require database_path('migrations/2026_07_10_120347_enforce_team_foundation_constraints.php');
    $migration->down();
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER team_activities_update_immutable
        BEFORE UPDATE ON team_activities
        BEGIN
            SELECT 1;
        END;
        SQL);

    try {
        expect(fn () => $migration->up())->toThrow(QueryException::class);

        $teamId = collect(DB::select('PRAGMA table_info(campaigns)'))
            ->firstWhere('name', 'team_id');

        expect($teamId->notnull)->toBe(0);
    } finally {
        config(['database.default' => $previousDefault]);
        DB::purge($connection);
    }
});
