<?php

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use App\TeamStatus;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('a signed in user creates and selects a team atomically', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Product Hiring'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $team = Team::query()->where('name', 'Product Hiring')->sole();

    expect($user->fresh()->current_team_id)->toBe($team->id)
        ->and($team->ownerMembership->user_id)->toBe($user->id)
        ->and($team->activities()->where('action', 'team_created')->where('actor_id', $user->id)->exists())->toBeTrue();
});

test('team names are required but may be duplicated', function () {
    Team::factory()->create(['name' => 'Product Hiring']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Product Hiring'])
        ->assertSessionHasNoErrors();

    expect(Team::query()->where('name', 'Product Hiring')->count())->toBe(2);
});

test('owners and administrators can rename an active team', function (string $role) {
    $team = Team::factory()->create(['name' => 'Old Team']);
    $user = match ($role) {
        'owner' => $team->ownerMembership->user,
        'administrator' => User::factory()->teamAdministrator($team)->create(),
    };
    $user->selectCurrentTeam($team);

    $this->actingAs($user)
        ->patch(route('teams.update', $team), ['name' => 'Renamed Team'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($team->fresh()->name)->toBe('Renamed Team')
        ->and($team->activities()->where('action', 'team_renamed')->where('actor_id', $user->id)->exists())->toBeTrue();
})->with(['owner', 'administrator']);

test('collaborators cannot rename and deactivated teams are read only', function () {
    $team = Team::factory()->create();
    $collaborator = User::factory()->teamCollaborator($team)->create();

    $this->actingAs($collaborator)
        ->patch(route('teams.update', $team), ['name' => 'Blocked'])
        ->assertForbidden();

    $team->update(['status' => TeamStatus::Deactivated, 'deactivated_at' => now()]);

    $this->actingAs($team->ownerMembership->user)
        ->patch(route('teams.update', $team), ['name' => 'Also Blocked'])
        ->assertForbidden();
});

test('a team can only be renamed while it is current', function () {
    $currentTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $owner = $otherTeam->ownerMembership->user;
    TeamMembership::factory()->for($currentTeam)->for($owner)->administrator()->create();
    $owner->selectCurrentTeam($currentTeam);

    $this->actingAs($owner)
        ->patch(route('teams.update', $otherTeam), ['name' => 'Blocked'])
        ->assertForbidden();

    expect($otherTeam->fresh()->name)->not->toBe('Blocked');
});

test('users switch only to teams where they have an active membership', function () {
    $firstTeam = Team::factory()->create();
    $secondTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->teamCollaborator($firstTeam)->teamAdministrator($secondTeam)->withCurrentTeam($firstTeam)->create();

    $this->actingAs($user)
        ->put(route('current-team.update'), ['team_id' => $secondTeam->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_team_id)->toBe($secondTeam->id)
        ->and(TeamMembership::query()->whereBelongsTo($user)->whereBelongsTo($secondTeam)->sole()->last_used_at)->not->toBeNull();

    $this->actingAs($user)
        ->put(route('current-team.update'), ['team_id' => $otherTeam->id])
        ->assertNotFound();

    expect($user->fresh()->current_team_id)->toBe($secondTeam->id);
});

test('current team selection persists across sign ins', function () {
    $team = Team::factory()->create();
    $user = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();

    $this->actingAs($user)->post(route('logout'));
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->current_team_id)->toBe($team->id);
});

test('shared data exposes teams contextual authority capabilities and read only state', function () {
    $activeTeam = Team::factory()->create(['name' => 'Active Team']);
    $deactivatedTeam = Team::factory()->deactivated()->create(['name' => 'History Team']);
    $user = User::factory()
        ->teamCollaborator($activeTeam)
        ->teamAdministrator($deactivatedTeam)
        ->withCurrentTeam($activeTeam)
        ->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.teams', 2)
            ->where('auth.currentTeam.role', TeamMembershipRole::Collaborator->value)
            ->where('auth.capabilities.manageCampaigns', true)
            ->where('auth.capabilities.renameTeam', false)
            ->where('auth.readOnly', false));

    $user->selectCurrentTeam($deactivatedTeam);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.currentTeam.status', TeamStatus::Deactivated->value)
            ->where('auth.capabilities.manageCampaigns', false)
            ->where('auth.capabilities.renameTeam', false)
            ->where('auth.readOnly', true));
});

test('a user without team membership receives the personal landing page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('auth.currentTeam', null)
            ->where('auth.capabilities.createTeam', true)
            ->where('personalLanding', true));
});
