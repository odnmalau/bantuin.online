<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\TeamMembershipRole;
use Illuminate\Support\Str;

test('only owners control administrator status while administrators manage collaborators', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $administrator = User::factory()->teamAdministrator($team)->create();
    $collaborator = User::factory()->teamCollaborator($team)->create();
    $owner->selectCurrentTeam($team);
    $administrator->selectCurrentTeam($team);
    $membership = $collaborator->activeTeamMemberships()->whereBelongsTo($team)->sole();

    $this->actingAs($administrator)
        ->patch(route('team-memberships.update', $membership), [
            'role' => TeamMembershipRole::Administrator->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('team-memberships.update', $membership), [
            'role' => TeamMembershipRole::Administrator->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(TeamMembershipRole::Administrator)
        ->and($team->activities()->where('action', 'team_membership_role_changed')->exists())->toBeTrue();

    $this->actingAs($administrator)
        ->patch(route('team-memberships.update', $membership), [
            'role' => TeamMembershipRole::Collaborator->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('team-memberships.update', $membership), [
            'role' => TeamMembershipRole::Collaborator->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(TeamMembershipRole::Collaborator);
});

test('membership removal follows the role matrix and revokes access immediately', function (string $actorRole, string $targetRole, bool $allowed) {
    $team = Team::factory()->create();
    $actor = match ($actorRole) {
        'owner' => $team->ownerMembership->user,
        'administrator' => User::factory()->teamAdministrator($team)->create(),
        'collaborator' => User::factory()->teamCollaborator($team)->create(),
    };
    $target = match ($targetRole) {
        'administrator' => User::factory()->teamAdministrator($team)->create(),
        'collaborator' => User::factory()->teamCollaborator($team)->create(),
    };
    $actor->selectCurrentTeam($team);
    $target->selectCurrentTeam($team);
    $membership = $target->activeTeamMemberships()->whereBelongsTo($team)->sole();

    $response = $this->actingAs($actor)->delete(route('team-memberships.destroy', $membership));

    if ($allowed) {
        $response->assertRedirect();
        expect($membership->fresh()->ended_at)->not->toBeNull()
            ->and($target->fresh()->current_team_id)->toBeNull();

        $this->actingAs($target)->get(route('admin.campaigns.index'))->assertForbidden();
    } else {
        $response->assertForbidden();
        expect($membership->fresh()->ended_at)->toBeNull();
    }
})->with([
    ['owner', 'administrator', true],
    ['owner', 'collaborator', true],
    ['administrator', 'collaborator', true],
    ['administrator', 'administrator', false],
    ['collaborator', 'collaborator', false],
]);

test('non owners leave voluntarily and receive the most recently used remaining team', function () {
    $currentTeam = Team::factory()->create();
    $fallbackTeam = Team::factory()->create();
    $user = User::factory()->teamCollaborator($currentTeam)->teamAdministrator($fallbackTeam)->create();
    $fallbackMembership = $user->activeTeamMemberships()->whereBelongsTo($fallbackTeam)->sole();
    $fallbackMembership->update(['last_used_at' => now()->subMinute()]);
    $user->selectCurrentTeam($currentTeam);

    $this->actingAs($user)
        ->delete(route('team-memberships.leave'))
        ->assertRedirect(route('dashboard'));

    expect($user->activeTeamMemberships()->whereBelongsTo($currentTeam)->exists())->toBeFalse()
        ->and($user->fresh()->current_team_id)->toBe($fallbackTeam->id)
        ->and($currentTeam->activities()->where('action', 'team_membership_departed')->exists())->toBeTrue();
});

test('the sole owner cannot leave or be removed', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);

    $this->actingAs($owner)
        ->delete(route('team-memberships.leave'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('team-memberships.destroy', $team->ownerMembership))
        ->assertForbidden();

    expect($team->ownerMembership()->exists())->toBeTrue();
});

test('a new accepted invitation creates a new membership term after departure', function () {
    $team = Team::factory()->create();
    $user = User::factory()->teamCollaborator($team)->create();
    $originalMembership = $user->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $originalMembership->update(['ended_at' => now()]);
    $plainToken = Str::random(64);
    TeamInvitation::factory()->for($team)->create([
        'email' => $user->email,
        'invited_by' => $team->ownerMembership->user_id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($user)
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('team-settings.edit'));

    expect($user->teamMemberships()->whereBelongsTo($team)->count())->toBe(2)
        ->and($originalMembership->fresh()->ended_at)->not->toBeNull();
});
