<?php

use App\CampaignInvitationStatus;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('owners issue administrator team invitations without creating membership', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);

    $this->actingAs($owner)
        ->post(route('team-invitations.store'), [
            'email' => 'Future.Member@Example.com',
            'role' => TeamMembershipRole::Administrator->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $invitation = TeamInvitation::query()->sole();

    expect($invitation->team_id)->toBe($team->id)
        ->and($invitation->email)->toBe('future.member@example.com')
        ->and($invitation->role)->toBe(TeamMembershipRole::Administrator)
        ->and($invitation->status)->toBe(TeamInvitationStatus::Pending)
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->expires_at->between(now()->addDays(13), now()->addDays(15)))->toBeTrue()
        ->and($team->memberships()->count())->toBe(1)
        ->and($team->activities()->where('action', 'team_invitation_issued')->sole()->after_state)->toBe([
            'email' => 'future.member@example.com',
            'role' => TeamMembershipRole::Administrator->value,
        ]);

    Notification::assertSentOnDemand(
        TeamInvitationNotification::class,
        fn (TeamInvitationNotification $notification, array $channels, object $notifiable): bool => $notification instanceof ShouldQueue
            && $notifiable->routes['mail'] === 'future.member@example.com',
    );
});

test('team invitation authority follows the offered role matrix', function (string $actorRole, string $offeredRole, bool $allowed) {
    Notification::fake();

    $team = Team::factory()->create();
    $actor = match ($actorRole) {
        'owner' => $team->ownerMembership->user,
        'administrator' => User::factory()->teamAdministrator($team)->create(),
        'collaborator' => User::factory()->teamCollaborator($team)->create(),
    };
    $actor->selectCurrentTeam($team);

    $response = $this->actingAs($actor)->post(route('team-invitations.store'), [
        'email' => fake()->unique()->safeEmail(),
        'role' => $offeredRole,
    ]);

    if ($allowed) {
        $response->assertSessionHasNoErrors();
        expect(TeamInvitation::query()->count())->toBe(1);
    } else {
        $response->assertForbidden();
        expect(TeamInvitation::query()->count())->toBe(0);
    }
})->with([
    ['owner', 'administrator', true],
    ['owner', 'collaborator', true],
    ['administrator', 'collaborator', true],
    ['administrator', 'administrator', false],
    ['collaborator', 'collaborator', false],
]);

test('authorized leaders revoke and resend only invitations they could issue', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $administrator = User::factory()->teamAdministrator($team)->create();
    $owner->selectCurrentTeam($team);
    $administrator->selectCurrentTeam($team);
    $plainToken = Str::random(64);
    $invitation = TeamInvitation::factory()->for($team)->create([
        'email' => 'member@example.com',
        'role' => TeamMembershipRole::Collaborator,
        'invited_by' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($administrator)
        ->delete(route('team-invitations.destroy', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->status)->toBe(TeamInvitationStatus::Revoked)
        ->and($team->activities()->where('action', 'team_invitation_revoked')->exists())->toBeTrue();

    $this->actingAs(User::factory()->create(['email' => 'member@example.com']))
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('login'));

    $expiredToken = Str::random(64);
    $expiredInvitation = TeamInvitation::factory()->for($team)->create([
        'email' => 'second@example.com',
        'role' => TeamMembershipRole::Collaborator,
        'invited_by' => $owner->id,
        'token_hash' => hash('sha256', $expiredToken),
        'status' => TeamInvitationStatus::Expired,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($administrator)
        ->post(route('team-invitations.resend', $expiredInvitation))
        ->assertRedirect();

    expect($expiredInvitation->fresh()->status)->toBe(TeamInvitationStatus::Pending)
        ->and($expiredInvitation->fresh()->token_hash)->not->toBe(hash('sha256', $expiredToken))
        ->and($expiredInvitation->fresh()->expires_at->isFuture())->toBeTrue()
        ->and($team->activities()->where('action', 'team_invitation_resent')->exists())->toBeTrue();

    $this->actingAs(User::factory()->create(['email' => 'second@example.com']))
        ->get(route('team-invitations.show', $expiredToken))
        ->assertRedirect(route('login'));
});

test('acceptance rechecks email inviter authority team state and candidate history', function (string $invalidState) {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $recipient = User::factory()->create(['email' => 'member@example.com']);
    $plainToken = Str::random(64);
    $invitation = TeamInvitation::factory()->for($team)->create([
        'email' => 'member@example.com',
        'invited_by' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    if ($invalidState === 'wrong email') {
        $recipient->update(['email' => 'other@example.com']);
    } elseif ($invalidState === 'expired') {
        $invitation->update(['expires_at' => now()->subMinute()]);
    } elseif ($invalidState === 'stale inviter') {
        $invitation->update(['invited_by' => User::factory()->create()->id]);
    } elseif ($invalidState === 'candidate history') {
        $campaign = Campaign::factory()->for($team)->create();
        CampaignInvitation::factory()->for($campaign)->forCandidate($recipient)->create();
    } elseif ($invalidState === 'deactivated team') {
        $team->update(['status' => 'deactivated', 'deactivated_at' => now()]);
    }

    $this->actingAs($recipient)
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($team->activeMemberships()->whereBelongsTo($recipient)->exists())->toBeFalse();
})->with(['wrong email', 'expired', 'stale inviter', 'candidate history', 'deactivated team']);

test('current or historical team membership blocks same team candidacy', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $formerMember = User::factory()->teamCollaborator($team)->create(['email' => 'former@example.com']);
    $formerMember->activeTeamMemberships()->whereBelongsTo($team)->sole()->update(['ended_at' => now()]);
    $campaign = Campaign::factory()->for($team)->active()->create(['created_by' => $owner->id]);
    $owner->selectCurrentTeam($team);

    $this->actingAs($owner)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'FORMER@example.com',
            'send_email' => false,
        ])
        ->assertSessionHasErrors('email');

    expect(CampaignInvitation::query()->whereBelongsTo($campaign)->exists())->toBeFalse();
});

test('campaign invitation acceptance rechecks historical team membership', function () {
    $team = Team::factory()->create();
    $candidate = User::factory()->teamCollaborator($team)->create(['email' => 'member@example.com']);
    $candidate->activeTeamMemberships()->whereBelongsTo($team)->sole()->update(['ended_at' => now()]);
    $campaign = Campaign::factory()->for($team)->active()->create();
    $plainToken = Str::random(64);
    $invitation = CampaignInvitation::factory()->for($campaign)->forCandidate($candidate)->create([
        'email' => $candidate->email,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($candidate)
        ->get(route('invites.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($invitation->fresh()->status)->toBe(CampaignInvitationStatus::Pending);
});

test('matching authenticated recipients atomically accept a team invitation', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $recipient = User::factory()->create(['email' => 'MEMBER@example.com']);
    $plainToken = Str::random(64);
    $invitation = TeamInvitation::factory()->for($team)->create([
        'email' => 'member@example.com',
        'invited_by' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($recipient)
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('team-settings.edit'));

    expect($invitation->fresh()->status)->toBe(TeamInvitationStatus::Accepted)
        ->and($invitation->fresh()->accepted_by)->toBe($recipient->id)
        ->and($recipient->activeTeamMemberships()->whereBelongsTo($team)->sole()->role)
        ->toBe(TeamMembershipRole::Collaborator)
        ->and($recipient->fresh()->current_team_id)->toBe($team->id)
        ->and($team->activities()->where('action', 'team_invitation_accepted')->exists())->toBeTrue();

    $this->actingAs($recipient)
        ->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($team->activeMemberships()->whereBelongsTo($recipient)->count())->toBe(1);
});

test('guest recipients complete team invitation acceptance after google sign in', function () {
    fakeGoogleAuthConfig();

    $team = Team::factory()->create();
    $plainToken = Str::random(64);
    $invitation = TeamInvitation::factory()->for($team)->create([
        'email' => 'member@example.com',
        'invited_by' => $team->ownerMembership->user_id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->get(route('team-invitations.show', $plainToken))
        ->assertRedirect(route('login'));

    fakeGoogleUserAuthentication(
        id: 'team-member-google-id',
        email: 'MEMBER@example.com',
        name: 'New Team Member',
    );

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('team-settings.edit'));

    expect($invitation->fresh()->status)->toBe(TeamInvitationStatus::Accepted)
        ->and($invitation->fresh()->acceptedBy->email)->toBe('MEMBER@example.com');
});
