<?php

use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\User;
use App\Notifications\OwnershipTransferNotification;
use App\OwnershipTransferStatus;
use App\TeamMembershipRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('owners propose one seven day ownership transfer to an active team member', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $recipient = User::factory()->teamCollaborator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $owner->selectCurrentTeam($team);

    $this->actingAs($owner)
        ->post(route('ownership-transfers.store'), ['membership_id' => $recipientMembership->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $transfer = OwnershipTransfer::query()->sole();

    expect($transfer->team_id)->toBe($team->id)
        ->and($transfer->owner_membership_id)->toBe($team->ownerMembership->id)
        ->and($transfer->recipient_membership_id)->toBe($recipientMembership->id)
        ->and($transfer->status)->toBe(OwnershipTransferStatus::Pending)
        ->and($transfer->expires_at->between(now()->addDays(6), now()->addDays(8)))->toBeTrue()
        ->and($team->ownerMembership->user_id)->toBe($owner->id);

    Notification::assertSentTo(
        $recipient,
        OwnershipTransferNotification::class,
        fn (OwnershipTransferNotification $notification): bool => $notification instanceof ShouldQueue,
    );

    $this->actingAs($owner)
        ->post(route('ownership-transfers.store'), ['membership_id' => $recipientMembership->id])
        ->assertSessionHasErrors('ownership_transfer');
});

test('only the intended recipient accepts ownership and the previous owner becomes administrator', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $recipient = User::factory()->teamAdministrator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $plainToken = Str::random(64);
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('ownership-transfers.show', $plainToken))
        ->assertForbidden();

    $this->actingAs($recipient)
        ->get(route('ownership-transfers.show', $plainToken))
        ->assertRedirect(route('team-settings.edit'));

    expect($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Accepted)
        ->and($team->ownerMembership()->sole()->user_id)->toBe($recipient->id)
        ->and($team->activeMemberships()->whereBelongsTo($owner)->sole()->role)->toBe(TeamMembershipRole::Administrator)
        ->and($recipient->fresh()->current_team_id)->toBe($team->id)
        ->and($team->activities()->where('action', 'ownership_transfer_accepted')->exists())->toBeTrue();

    $this->actingAs($recipient)
        ->get(route('ownership-transfers.show', $plainToken))
        ->assertRedirect(route('login'));
});

test('guest recipients resume ownership transfer acceptance after google sign in', function () {
    fakeGoogleAuthConfig();

    $team = Team::factory()->create();
    $recipient = User::factory()->teamCollaborator($team)->create(['email' => 'recipient@example.com']);
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $plainToken = Str::random(64);
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->get(route('ownership-transfers.show', $plainToken))
        ->assertRedirect(route('login'));

    fakeGoogleUserAuthentication(
        id: $recipient->google_id,
        email: $recipient->email,
        name: $recipient->name,
    );

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('team-settings.edit'));

    expect($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Accepted)
        ->and($team->ownerMembership()->sole()->user_id)->toBe($recipient->id);
});

test('owners revoke pending transfers and expired transfers cannot be accepted', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $recipient = User::factory()->teamCollaborator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $owner->selectCurrentTeam($team);
    $plainToken = Str::random(64);
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($owner)
        ->delete(route('ownership-transfers.destroy', $transfer))
        ->assertRedirect();

    expect($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Revoked);

    $expiredToken = Str::random(64);
    $expiredTransfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
        'token_hash' => hash('sha256', $expiredToken),
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($recipient)
        ->get(route('ownership-transfers.show', $expiredToken))
        ->assertRedirect(route('login'));

    expect($expiredTransfer->fresh()->status)->toBe(OwnershipTransferStatus::Expired)
        ->and($team->ownerMembership()->sole()->user_id)->toBe($owner->id);
});
