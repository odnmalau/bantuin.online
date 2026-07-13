<?php

use App\CampaignInvitationStatus;
use App\Jobs\SendCampaignExamInvitationEmail;
use App\Mail\CampaignExamInvitationMail;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\Services\CampaignInvitationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('admin can create a campaign exam invitation', function () {
    Mail::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->active()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'Candidate@Example.com',
            'send_email' => false,
        ]);

    $response->assertRedirect()
        ->assertSessionHas('campaign_invite_url');

    $invitation = CampaignInvitation::query()->sole();

    expect($invitation)
        ->campaign_id->toBe($campaign->id)
        ->email->toBe('candidate@example.com')
        ->status->toBe(CampaignInvitationStatus::Pending)
        ->and(session('campaign_invite_url'))->toContain('/invites/');

    Mail::assertNothingSent();
});

test('creating an invitation queues the exam invite email job', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->active()->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'candidate@example.com',
            'send_email' => true,
        ])
        ->assertRedirect();

    Queue::assertPushed(SendCampaignExamInvitationEmail::class);
});

test('exam invite email job sends mailable with invite link', function () {
    Mail::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()->createWithPlainToken([
        'campaign_id' => $campaign->id,
        'email' => 'candidate@example.com',
        'invited_by' => $admin->id,
    ]);

    (new SendCampaignExamInvitationEmail($invitation, $plainToken))
        ->handle(app(CampaignInvitationService::class));

    Mail::assertSent(
        CampaignExamInvitationMail::class,
        fn (CampaignExamInvitationMail $mail): bool => str_contains($mail->render(), $campaign->team->name),
    );
});

test('exam invite email job rechecks its campaign team boundary', function () {
    Mail::fake();

    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()->createWithPlainToken([
        'campaign_id' => $campaign->id,
        'email' => 'candidate@example.com',
    ]);
    $job = new SendCampaignExamInvitationEmail($invitation, $plainToken);
    $sentAt = $invitation->sent_at;
    $campaign->team->update(['status' => 'deactivated', 'deactivated_at' => now()]);

    $job->handle(app(CampaignInvitationService::class));

    Mail::assertNothingSent();
    expect($invitation->fresh()->sent_at)->toEqual($sentAt);
});

test('campaign exam invitation email job encrypts its queue payload', function () {
    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()->createWithPlainToken([
        'campaign_id' => $campaign->id,
        'email' => 'candidate@example.com',
    ]);

    expect(new SendCampaignExamInvitationEmail($invitation, $plainToken))
        ->toBeInstanceOf(ShouldBeEncrypted::class);
});

test('campaign exam invitation email job ignores a stale token', function () {
    Mail::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $staleToken] = CampaignInvitation::factory()->createWithPlainToken([
        'campaign_id' => $campaign->id,
        'email' => 'candidate@example.com',
        'invited_by' => $admin->id,
    ]);
    $sentAt = $invitation->sent_at;
    $staleJob = new SendCampaignExamInvitationEmail($invitation, $staleToken);
    ['plain_token' => $currentToken] = CampaignInvitation::issueToken($invitation->fresh());

    $staleJob->handle(app(CampaignInvitationService::class));

    Mail::assertNothingSent();
    expect($invitation->fresh()->sent_at)->toEqual($sentAt);

    (new SendCampaignExamInvitationEmail($invitation->fresh(), $currentToken))
        ->handle(app(CampaignInvitationService::class));

    Mail::assertSent(CampaignExamInvitationMail::class, 1);
});

test('delivery claim serializes sending against revoke and token rotation', function () {
    Queue::fake();

    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->createWithPlainToken([
            'sent_at' => null,
        ]);
    $invitations = app(CampaignInvitationService::class);
    $deliveryClaim = (string) Str::uuid();

    expect($invitations->claimEmailDelivery(
        $invitation->id,
        $plainToken,
        $deliveryClaim,
        $campaign->team_id,
    ))->not->toBeNull()
        ->and(fn () => $invitations->revoke($invitation->fresh()))
        ->toThrow(ValidationException::class, 'currently being sent')
        ->and(fn () => $invitations->resend($invitation->fresh()))
        ->toThrow(ValidationException::class, 'currently being sent');

    $invitations->releaseEmailDelivery($invitation->id, $deliveryClaim);
    $invitations->resend($invitation->fresh());

    expect($invitations->completeEmailDelivery($invitation->id, $plainToken, $deliveryClaim))->toBeFalse()
        ->and($invitation->fresh()->sent_at)->toBeNull()
        ->and($invitation->fresh()->token_hash)->not->toBe(hash('sha256', $plainToken));
});

test('revoked invitation cannot be claimed by a queued email job', function () {
    Mail::fake();

    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->createWithPlainToken([
            'sent_at' => null,
        ]);
    $job = new SendCampaignExamInvitationEmail($invitation, $plainToken);

    app(CampaignInvitationService::class)->revoke($invitation);
    $job->handle(app(CampaignInvitationService::class));

    Mail::assertNothingSent();
    expect($invitation->fresh())
        ->status->toBe(CampaignInvitationStatus::Revoked)
        ->sent_at->toBeNull()
        ->send_claim->toBeNull();
});

test('owner can revoke a pending Campaign Invitation', function () {
    $owner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($owner->currentTeam)->active()->create([
        'created_by' => $owner->id,
    ]);
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->createWithPlainToken([
            'email' => 'candidate@example.com',
            'invited_by' => $owner->id,
        ]);

    $this->actingAs($owner)
        ->delete(route('admin.campaigns.invitations.destroy', [$campaign, $invitation]))
        ->assertRedirect();

    expect($invitation->fresh()->status)->toBe(CampaignInvitationStatus::Revoked);

    $this->get(route('invites.show', $plainToken))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});

test('owner can resend a pending Campaign Invitation and rotate its token', function () {
    Queue::fake();

    $owner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($owner->currentTeam)->active()->create([
        'created_by' => $owner->id,
    ]);
    ['invitation' => $invitation, 'plain_token' => $oldToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->createWithPlainToken([
            'email' => 'candidate@example.com',
            'invited_by' => $owner->id,
        ]);
    $oldTokenHash = $invitation->token_hash;

    $this->actingAs($owner)
        ->post(route('admin.campaigns.invitations.resend', [$campaign, $invitation]))
        ->assertRedirect();

    $invitation = $invitation->fresh();

    expect($invitation)
        ->status->toBe(CampaignInvitationStatus::Pending)
        ->token_hash->not->toBe($oldTokenHash)
        ->expires_at->isFuture()->toBeTrue()
        ->sent_at->toBeNull();

    Queue::assertPushed(SendCampaignExamInvitationEmail::class);

    $this->get(route('invites.show', $oldToken))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});

test('accepted Campaign Invitations cannot be revoked', function () {
    $owner = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create([
        'email' => 'candidate@example.com',
    ]);
    $campaign = Campaign::factory()->for($owner->currentTeam)->active()->create([
        'created_by' => $owner->id,
    ]);
    $invitation = CampaignInvitation::factory()->for($campaign)->accepted($candidate)->create([
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.invitations.destroy', [$campaign, $invitation]))
        ->assertRedirect(route('admin.campaigns.show', $campaign))
        ->assertSessionHasErrors('invitation');

    expect($invitation->fresh()->status)->toBe(CampaignInvitationStatus::Accepted);
});

test('user without campaign permission cannot resend a Campaign Invitation', function () {
    Queue::fake();

    $owner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($owner->currentTeam)->active()->create([
        'created_by' => $owner->id,
    ]);
    $invitation = CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'candidate@example.com',
        'invited_by' => $owner->id,
    ]);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('admin.campaigns.invitations.resend', [$campaign, $invitation]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('invite link stores pending redemption and redirects to login', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create();
    $invitation = CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'candidate@example.com',
        'invited_by' => $admin->id,
    ]);
    ['plain_token' => $plainToken] = CampaignInvitation::issueToken($invitation->fresh());

    $this->get(route('invites.show', $plainToken))
        ->assertRedirect(route('login'))
        ->assertSessionHas(CampaignInvitationService::SESSION_PENDING_ID);
});

test('google login after invite accepts assignment and opens campaign exam', function () {
    fakeGoogleAuthConfig();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $invitation = CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'candidate@example.com',
        'invited_by' => $admin->id,
    ]);
    ['plain_token' => $plainToken] = CampaignInvitation::issueToken($invitation->fresh());

    $this->get(route('invites.show', $plainToken))
        ->assertRedirect(route('login'));

    fakeGoogleUserAuthentication(
        id: 'google-invite-user',
        email: 'candidate@example.com',
        name: 'Candidate User',
    );

    $this->withSession([
        'url.intended' => route('profile.edit'),
    ])->get(route('auth.google.callback'))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    $invitation = CampaignInvitation::query()->sole();

    expect($invitation)
        ->status->toBe(CampaignInvitationStatus::Accepted)
        ->user_id->not->toBeNull();
});

test('invite redemption fails when google email does not match invitation', function () {
    fakeGoogleAuthConfig();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create();

    $invitation = CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'invited@example.com',
        'invited_by' => $admin->id,
    ]);
    ['plain_token' => $plainToken] = CampaignInvitation::issueToken($invitation->fresh());

    $this->get(route('invites.show', $plainToken));

    fakeGoogleUserAuthentication(
        id: 'google-wrong-user',
        email: 'other@example.com',
        name: 'Other User',
    );

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(CampaignInvitation::query()->sole()->status)->toBe(CampaignInvitationStatus::Pending);
});

test('authenticated candidate can redeem invite link directly', function () {
    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create([
        'email' => 'candidate@example.com',
    ]);
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $invitation = CampaignInvitation::factory()
        ->for($campaign)
        ->forCandidate($candidate)
        ->create([
            'invited_by' => $admin->id,
        ]);
    ['plain_token' => $plainToken] = CampaignInvitation::issueToken($invitation->fresh());

    $this->actingAs($candidate)
        ->get(route('invites.show', $plainToken))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));
});

test('team member can redeem a campaign invitation owned by another team', function () {
    $teamMember = User::factory()->teamOwner()->create([
        'email' => 'candidate@example.com',
    ]);
    $otherTeamOwner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($otherTeamOwner->currentTeam)->active()->create([
        'created_by' => $otherTeamOwner->id,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->forCandidate($teamMember)
        ->createWithPlainToken();

    expect($teamMember->current_team_id)->not->toBe($campaign->team_id);

    $this->actingAs($teamMember)
        ->get(route('invites.show', $plainToken))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    expect($invitation->fresh())
        ->status->toBe(CampaignInvitationStatus::Accepted)
        ->user_id->toBe($teamMember->id);
});

test('accepted campaign invitation links cannot be replayed', function () {
    $candidate = User::factory()->create([
        'email' => 'candidate@example.com',
    ]);
    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()
        ->for($campaign)
        ->accepted($candidate)
        ->createWithPlainToken();

    $this->actingAs($candidate)
        ->get(route('invites.show', $plainToken))
        ->assertRedirect(route('login'));

    expect($invitation->fresh())
        ->status->toBe(CampaignInvitationStatus::Accepted)
        ->user_id->toBe($candidate->id);
});

test('issuing another invitation does not reset accepted candidate history', function () {
    $owner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($owner->currentTeam)->active()->create([
        'created_by' => $owner->id,
    ]);
    $candidate = User::factory()->create([
        'email' => 'candidate@example.com',
    ]);
    $invitation = CampaignInvitation::factory()->for($campaign)->accepted($candidate)->create();
    $originalTokenHash = $invitation->token_hash;

    $this->actingAs($owner)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'CANDIDATE@example.com',
            'send_email' => false,
        ])
        ->assertSessionHasErrors('email');

    expect($invitation->fresh())
        ->status->toBe(CampaignInvitationStatus::Accepted)
        ->user_id->toBe($candidate->id)
        ->token_hash->toBe($originalTokenHash)
        ->and(CampaignInvitation::query()->whereBelongsTo($campaign)->count())->toBe(1);
});

test('candidate cannot create invitations', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'candidate@example.com',
        ])
        ->assertForbidden();
});
