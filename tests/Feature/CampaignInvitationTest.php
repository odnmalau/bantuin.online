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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('admin can create a campaign exam invitation', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->active()->create();

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

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->active()->create();

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

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->active()->create();
    ['invitation' => $invitation, 'plain_token' => $plainToken] = CampaignInvitation::factory()->createWithPlainToken([
        'campaign_id' => $campaign->id,
        'email' => 'candidate@example.com',
        'invited_by' => $admin->id,
    ]);

    (new SendCampaignExamInvitationEmail($invitation, $plainToken))
        ->handle(app(CampaignInvitationService::class));

    Mail::assertSent(CampaignExamInvitationMail::class);
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

test('invite link stores pending redemption and redirects to login', function () {
    $admin = User::factory()->admin()->create();
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

    $admin = User::factory()->admin()->create();
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

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    $invitation = CampaignInvitation::query()->sole();

    expect($invitation)
        ->status->toBe(CampaignInvitationStatus::Accepted)
        ->user_id->not->toBeNull();
});

test('invite redemption fails when google email does not match invitation', function () {
    fakeGoogleAuthConfig();

    $admin = User::factory()->admin()->create();
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
    $admin = User::factory()->admin()->create();
    $candidate = User::factory()->candidate()->create([
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

test('candidate cannot create invitations', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.invitations.store', $campaign), [
            'email' => 'candidate@example.com',
        ])
        ->assertForbidden();
});
