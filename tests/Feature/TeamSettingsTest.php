<?php

use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\TeamInvitation;
use App\Models\User;
use App\TeamMembershipRole;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('owners and administrators view team activity and capability aware administration data', function (string $role) {
    $team = Team::factory()->create(['name' => 'Platform Hiring']);
    $viewer = $role === 'owner'
        ? $team->ownerMembership->user
        : User::factory()->teamAdministrator($team)->create();
    $collaborator = User::factory()->teamCollaborator($team)->create();
    $viewer->selectCurrentTeam($team);
    TeamInvitation::factory()->for($team)->create([
        'invited_by' => $team->ownerMembership->user_id,
        'email' => 'pending@example.com',
    ]);
    TeamActivity::factory()->for($team)->create([
        'actor_id' => $viewer->id,
        'action' => 'team_membership_role_changed',
        'before_state' => ['role' => TeamMembershipRole::Collaborator->value],
        'after_state' => ['role' => TeamMembershipRole::Administrator->value],
    ]);

    $this->actingAs($viewer)
        ->get(route('team-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/team')
            ->where('team.name', 'Platform Hiring')
            ->has('members', $role === 'owner' ? 2 : 3)
            ->has('invitations', 1)
            ->has('activities', 1)
            ->where('can.viewActivity', true)
            ->where('can.transferOwnership', $role === 'owner')
            ->where('members.'.($role === 'owner' ? 1 : 2).'.user_id', $collaborator->id));
})->with(['owner', 'administrator']);

test('collaborators can view team membership but cannot view team activity or administration controls', function () {
    $team = Team::factory()->create();
    $collaborator = User::factory()->teamCollaborator($team)->create();
    $collaborator->selectCurrentTeam($team);
    TeamActivity::factory()->for($team)->create(['action' => 'sensitive_action']);

    $this->actingAs($collaborator)
        ->get(route('team-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/team')
            ->has('members', 2)
            ->missing('activities')
            ->where('can.inviteAdministrator', false)
            ->where('can.inviteCollaborator', false)
            ->where('can.viewActivity', false));
});

test('team activity responses contain no invitation token hashes or sensitive payloads', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);
    TeamActivity::factory()->for($team)->create([
        'action' => 'team_invitation_issued',
        'after_state' => ['email' => 'member@example.com', 'role' => 'collaborator'],
    ]);

    $response = $this->actingAs($owner)->get(route('team-settings.edit'));

    $response->assertOk();
    expect($response->getContent())
        ->not->toContain('token_hash')
        ->not->toContain('resume')
        ->not->toContain('answers_payload');
});
