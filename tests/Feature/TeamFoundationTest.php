<?php

use App\Models\Campaign;
use App\Models\PlatformOperatorAuthority;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('contextual factory states establish team authority independently from legacy roles', function () {
    $owner = User::factory()->admin()->teamOwner()->create();
    $team = $owner->currentTeam()->sole();
    $administrator = User::factory()->teamAdministrator($team)->create();
    $collaborator = User::factory()->teamCollaborator($team)->create();

    expect($team->status)->toBe(TeamStatus::Active)
        ->and($team->activeMemberships()->where('role', TeamMembershipRole::Owner)->sole()->user_id)->toBe($owner->id)
        ->and($administrator->activeTeamMemberships()->sole()->role)->toBe(TeamMembershipRole::Administrator)
        ->and($collaborator->activeTeamMemberships()->sole()->role)->toBe(TeamMembershipRole::Collaborator)
        ->and($owner->role->value)->toBe('admin');
});

test('a team prevents duplicate active memberships and effective owners', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->ownedBy($owner)->create();
    $member = User::factory()->create();

    TeamMembership::factory()->for($team)->for($member)->collaborator()->create();

    expect(fn () => TeamMembership::factory()->for($team)->for($member)->administrator()->create())
        ->toThrow(QueryException::class)
        ->and(fn () => TeamMembership::factory()->for($team)->for(User::factory())->owner()->create())
        ->toThrow(QueryException::class)
        ->and(fn () => $team->ownerMembership->update(['ended_at' => now()]))
        ->toThrow(LogicException::class);
});

test('team creation requires an owner-aware application path', function () {
    $owner = User::factory()->create();
    $team = Team::createForOwner($owner, 'Owner First Team');

    expect($team->ownerMembership->user_id)->toBe($owner->id)
        ->and(fn () => Team::query()->create(['name' => 'Ownerless Team']))
        ->toThrow(LogicException::class)
        ->and(Team::query()->where('name', 'Ownerless Team')->exists())->toBeFalse();
});

test('ended membership remains durable and permits a later active term', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $endedMembership = TeamMembership::factory()
        ->for($team)
        ->for($member)
        ->collaborator()
        ->ended()
        ->create();

    $activeMembership = TeamMembership::factory()
        ->for($team)
        ->for($member)
        ->administrator()
        ->create();

    expect($endedMembership->isActive())->toBeFalse()
        ->and($activeMembership->isActive())->toBeTrue()
        ->and($team->memberships()->whereBelongsTo($member)->count())->toBe(2);
});

test('deactivated team and current team factory states preserve contextual identity', function () {
    $team = Team::factory()->deactivated()->create();
    $member = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();

    expect($team->status)->toBe(TeamStatus::Deactivated)
        ->and($team->deactivated_at)->not->toBeNull()
        ->and($member->currentTeam->is($team))->toBeTrue();
});

test('platform operator authority is independent from team membership', function () {
    $operator = User::factory()->platformOperator()->create();

    expect($operator->platformOperatorAuthorities()->active()->count())->toBe(1)
        ->and($operator->teamMemberships()->count())->toBe(0)
        ->and($operator->role->value)->toBe('candidate');

    $authority = $operator->platformOperatorAuthorities()->sole();
    expect($authority)->toBeInstanceOf(PlatformOperatorAuthority::class);
});

test('current team selection requires an active membership', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(fn () => $user->selectCurrentTeam($team))->toThrow(LogicException::class)
        ->and($user->fresh()->current_team_id)->toBeNull();
});

test('team activity is append only in the database', function () {
    $activity = TeamActivity::factory()->create();

    expect(fn () => DB::table('team_activities')->where('id', $activity->id)->update(['action' => 'changed']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('team_activities')->where('id', $activity->id)->delete())
        ->toThrow(QueryException::class);
});

test('team names are display values and may match with any casing', function () {
    Team::factory()->create(['name' => 'Acme']);
    Team::factory()->create(['name' => 'Acme']);
    Team::factory()->create(['name' => 'ACME']);

    expect(Team::query()->count())->toBe(3);
});

test('campaign team ownership is required and immutable', function () {
    $campaign = Campaign::factory()->create();
    $originalTeam = $campaign->team;
    $otherTeam = Team::factory()->create();

    expect($originalTeam)->toBeInstanceOf(Team::class)
        ->and(fn () => $campaign->update(['team_id' => $otherTeam->id]))
        ->toThrow(LogicException::class)
        ->and($campaign->fresh()->team->is($originalTeam))->toBeTrue();
});
