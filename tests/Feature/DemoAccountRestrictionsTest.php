<?php

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\OwnershipTransfer;
use App\Models\TeamInvitation;
use App\Models\User;
use App\TeamMembershipRole;
use Illuminate\Support\Str;

test('demo accounts cannot update or delete their profiles', function (string $email, string $method, string $routeName, array $data) {
    $user = User::factory()->create([
        'name' => 'Original Demo Name',
        'email' => $email,
    ]);

    $this->actingAs($user)
        ->call($method, route($routeName), $data)
        ->assertForbidden();

    expect($user->fresh()?->name)->toBe('Original Demo Name')
        ->and($user->fresh()?->trashed())->toBeFalse();
})->with([
    'admin profile update' => [User::DEMO_ADMIN_EMAIL, 'PATCH', 'profile.update', ['name' => 'Changed Name']],
    'candidate profile update' => [User::DEMO_CANDIDATE_EMAIL, 'PATCH', 'profile.update', ['name' => 'Changed Name']],
    'admin profile deletion' => [User::DEMO_ADMIN_EMAIL, 'DELETE', 'profile.destroy', ['confirmation' => 'DELETE']],
    'candidate profile deletion' => [User::DEMO_CANDIDATE_EMAIL, 'DELETE', 'profile.destroy', ['confirmation' => 'DELETE']],
]);

test('demo team lifecycle ownership and membership changes are forbidden', function () {
    $this->post(route('auth.demo.admin'))->assertRedirect(route('dashboard'));

    $admin = User::query()->where('email', User::DEMO_ADMIN_EMAIL)->firstOrFail();
    $team = $admin->currentTeam()->firstOrFail();
    $member = User::factory()->teamCollaborator($team)->create();
    $member->selectCurrentTeam($team);
    $membership = $member->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $invitation = TeamInvitation::factory()->for($team)->create(['invited_by' => $admin->id]);

    $adminRequests = [
        ['PATCH', route('teams.update', $team), ['name' => 'Renamed Demo Workspace']],
        ['POST', route('teams.deactivate', $team), []],
        ['DELETE', route('teams.destroy', $team), []],
        ['POST', route('ownership-transfers.store'), ['membership_id' => $membership->id]],
        ['POST', route('team-invitations.store'), ['email' => 'member@example.com', 'role' => TeamMembershipRole::Collaborator->value]],
        ['DELETE', route('team-invitations.destroy', $invitation), []],
        ['POST', route('team-invitations.resend', $invitation), []],
        ['PATCH', route('team-memberships.update', $membership), ['role' => TeamMembershipRole::Administrator->value]],
        ['DELETE', route('team-memberships.destroy', $membership), []],
    ];

    foreach ($adminRequests as [$method, $url, $data]) {
        $this->actingAs($admin)->call($method, $url, $data)->assertForbidden();
    }

    $this->actingAs($member)
        ->delete(route('team-memberships.leave'))
        ->assertForbidden();

    expect($team->fresh())->not->toBeNull()
        ->and($team->fresh()->name)->toBe('Demo Team')
        ->and($membership->fresh()->role)->toBe(TeamMembershipRole::Collaborator)
        ->and($membership->fresh()->ended_at)->toBeNull();
});

test('demo admin can still invite campaign candidates', function () {
    $this->post(route('auth.demo.admin'));

    $admin = User::query()->where('email', User::DEMO_ADMIN_EMAIL)->firstOrFail();
    $team = $admin->currentTeam()->firstOrFail();
    $campaign = Campaign::factory()->for($team)->for($admin, 'creator')->active()->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'candidate@example.com',
            'send_email' => false,
        ])
        ->assertSessionHasNoErrors();

    expect(CampaignInvitation::query()->whereBelongsTo($campaign)->exists())->toBeTrue();
});

test('platform operators cannot bypass demo team restrictions', function (string $action, string $errorKey) {
    $this->post(route('auth.demo.admin'));

    $admin = User::query()->where('email', User::DEMO_ADMIN_EMAIL)->firstOrFail();
    $team = $admin->currentTeam()->firstOrFail();
    $member = User::factory()->teamCollaborator($team)->create();
    $membership = $member->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $operator = User::factory()->platformOperator()->create();

    $response = match ($action) {
        'deactivate' => $this->actingAs($operator)->post(route('support.teams.deactivate', $team), [
            'reason' => 'Demo protection test',
        ]),
        'invite' => $this->actingAs($operator)->post(route('support.teams.membership-repairs.store', $team), [
            'email' => 'new-member@example.com',
            'role' => TeamMembershipRole::Collaborator->value,
            'reason' => 'Demo protection test',
        ]),
        'transfer' => $this->actingAs($operator)->post(route('support.teams.ownership-transfers.store', $team), [
            'membership_id' => $membership->id,
            'reason' => 'Demo protection test',
        ]),
    };

    $response->assertSessionHasErrors($errorKey);
})->with([
    'deactivate' => ['deactivate', 'team'],
    'team invitation' => ['invite', 'invitation'],
    'ownership transfer' => ['transfer', 'membership_id'],
]);

test('existing team invitations cannot add members to the demo team', function () {
    $this->post(route('auth.demo.admin'));

    $admin = User::query()->where('email', User::DEMO_ADMIN_EMAIL)->firstOrFail();
    $team = $admin->currentTeam()->firstOrFail();
    $recipient = User::factory()->create(['email' => 'invited-member@example.com']);
    $plainToken = Str::random(64);
    TeamInvitation::factory()->for($team)->create([
        'email' => $recipient->email,
        'invited_by' => $admin->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($recipient)
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($recipient->activeTeamMemberships()->whereBelongsTo($team)->exists())->toBeFalse();
});

test('existing ownership transfers cannot change the demo team owner', function () {
    $this->post(route('auth.demo.admin'));

    $admin = User::query()->where('email', User::DEMO_ADMIN_EMAIL)->firstOrFail();
    $team = $admin->currentTeam()->firstOrFail();
    $recipient = User::factory()->teamCollaborator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $plainToken = Str::random(64);
    OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($recipient)
        ->get(route('ownership-transfers.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($team->ownerMembership()->sole()->user_id)->toBe($admin->id);
});
