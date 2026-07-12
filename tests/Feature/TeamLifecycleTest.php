<?php

use App\CampaignStatus;
use App\ExamSessionStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\ExamSession;
use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\ExamSessionService;
use App\Services\OwnershipTransferService;
use App\Services\TeamLifecycleService;
use App\TeamInvitationStatus;
use App\TeamStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

test('only the owner can deactivate and reactivate a team', function (string $role) {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $actor = match ($role) {
        'owner' => $owner,
        'administrator' => User::factory()->teamAdministrator($team)->create(),
        'collaborator' => User::factory()->teamCollaborator($team)->create(),
        'non-member' => User::factory()->create(),
    };

    if ($role !== 'non-member') {
        $actor->selectCurrentTeam($team);
    }

    $response = $this->actingAs($actor)->post(route('teams.deactivate', $team));

    if ($role !== 'owner') {
        $response->assertForbidden();
        expect($team->fresh()->status)->toBe(TeamStatus::Active);

        return;
    }

    $response->assertSessionHasNoErrors()->assertRedirect();
    $team->refresh();

    expect($team->status)->toBe(TeamStatus::Deactivated)
        ->and($team->deactivated_at)->not->toBeNull()
        ->and($team->deactivated_by)->toBe($owner->id)
        ->and($team->activities()->where('action', 'team_deactivated')->where('actor_id', $owner->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('teams.reactivate', $team))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $team->refresh();

    expect($team->status)->toBe(TeamStatus::Active)
        ->and($team->deactivated_at)->toBeNull()
        ->and($team->deactivated_by)->toBeNull()
        ->and($team->activities()->where('action', 'team_reactivated')->where('actor_id', $owner->id)->exists())->toBeTrue();
})->with(['owner', 'administrator', 'collaborator', 'non-member']);

test('an in progress exam prevents deactivation without interrupting the exam', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);
    $campaign = Campaign::factory()->for($team)->active()->create();
    $examSession = ExamSession::factory()->for($campaign)->create();

    $this->actingAs($owner)
        ->from(route('team-settings.edit'))
        ->post(route('teams.deactivate', $team))
        ->assertSessionHasErrors('team')
        ->assertRedirect(route('team-settings.edit'));

    expect($team->fresh()->status)->toBe(TeamStatus::Active)
        ->and($examSession->fresh()->status)->toBe(ExamSessionStatus::InProgress)
        ->and($team->activities()->where('action', 'team_deactivated')->exists())->toBeFalse();
});

test('lifecycle services recheck ownership after acquiring the team lock', function () {
    $team = Team::factory()->create();
    $previousOwner = $team->ownerMembership->user;
    $recipient = User::factory()->teamAdministrator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
    ]);
    app(OwnershipTransferService::class)->accept($transfer, $recipient);

    expect(fn () => app(TeamLifecycleService::class)->deactivate($team, $previousOwner))
        ->toThrow(AuthorizationException::class);
    expect($team->fresh()->status)->toBe(TeamStatus::Active);
});

test('reactivation does not change campaign statuses', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);
    $campaigns = collect(CampaignStatus::cases())->mapWithKeys(fn (CampaignStatus $status): array => [
        $status->value => Campaign::factory()->for($team)->create(['status' => $status]),
    ]);

    $this->actingAs($owner)->post(route('teams.deactivate', $team))->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('teams.reactivate', $team))->assertSessionHasNoErrors();

    $campaigns->each(fn (Campaign $campaign, string $status) => expect($campaign->fresh()->status->value)->toBe($status));
});

test('only the owner can delete an empty team while retaining its tombstone and activity', function () {
    $team = Team::factory()->create(['name' => 'Unused Hiring Team']);
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);
    $otherTeam = Team::factory()->create();
    TeamMembership::factory()->for($otherTeam)->for($owner)->collaborator()->create(['last_used_at' => now()->subMinute()]);

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Team::query()->find($team->id))->toBeNull()
        ->and(Team::withTrashed()->find($team->id)?->name)->toBe('Unused Hiring Team')
        ->and($team->activities()->where('action', 'team_deleted')->where('actor_id', $owner->id)->exists())->toBeTrue()
        ->and($owner->fresh()->current_team_id)->toBe($otherTeam->id);

    $this->actingAs($owner)
        ->put(route('current-team.update'), ['team_id' => $team->id])
        ->assertNotFound();
});

test('non owners cannot delete an empty team', function () {
    $team = Team::factory()->create();
    $administrator = User::factory()->teamAdministrator($team)->withCurrentTeam($team)->create();

    $this->actingAs($administrator)
        ->delete(route('teams.destroy', $team))
        ->assertForbidden();

    expect($team->fresh())->not->toBeNull();
});

test('a team is not empty when it has domain or membership history', function (string $history) {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);

    match ($history) {
        'campaign' => Campaign::factory()->for($team)->create(),
        'pending invitation' => TeamInvitation::factory()->for($team)->create(['invited_by' => $owner->id]),
        'current membership' => TeamMembership::factory()->for($team)->collaborator()->create(),
        'ended membership' => TeamMembership::factory()->for($team)->collaborator()->create(['ended_at' => now()]),
    };

    $this->actingAs($owner)
        ->from(route('team-settings.edit'))
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasErrors('team')
        ->assertRedirect(route('team-settings.edit'));

    expect($team->fresh())->not->toBeNull();
})->with(['campaign', 'pending invitation', 'current membership', 'ended membership']);

test('non pending invitations do not prevent deleting an otherwise empty team', function (TeamInvitationStatus $status) {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);
    TeamInvitation::factory()->for($team)->create([
        'invited_by' => $owner->id,
        'status' => $status,
        'revoked_at' => $status === TeamInvitationStatus::Revoked ? now() : null,
    ]);

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($team);
})->with([
    TeamInvitationStatus::Accepted,
    TeamInvitationStatus::Revoked,
    TeamInvitationStatus::Expired,
]);

test('a deactivated team rejects every team administration mutation', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $member = User::factory()->teamCollaborator($team)->create();
    $memberMembership = $member->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $invitation = TeamInvitation::factory()->for($team)->create(['invited_by' => $owner->id]);
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $memberMembership->id,
    ]);
    $owner->selectCurrentTeam($team);
    $member->selectCurrentTeam($team);
    $team->update(['status' => TeamStatus::Deactivated, 'deactivated_at' => now()]);

    $ownerRequests = [
        ['PATCH', route('teams.update', $team), ['name' => 'Blocked']],
        ['POST', route('team-invitations.store'), ['email' => 'new@example.com', 'role' => 'collaborator']],
        ['DELETE', route('team-invitations.destroy', $invitation), []],
        ['POST', route('team-invitations.resend', $invitation), []],
        ['PATCH', route('team-memberships.update', $memberMembership), ['role' => 'administrator']],
        ['DELETE', route('team-memberships.destroy', $memberMembership), []],
        ['POST', route('ownership-transfers.store'), ['membership_id' => $memberMembership->id]],
        ['DELETE', route('ownership-transfers.destroy', $transfer), []],
    ];

    foreach ($ownerRequests as [$method, $url, $data]) {
        $this->actingAs($owner)->call($method, $url, $data)->assertForbidden();
    }

    $this->actingAs($member)
        ->delete(route('team-memberships.leave'))
        ->assertForbidden();

    expect($team->fresh()->name)->not->toBe('Blocked')
        ->and($invitation->fresh()->status)->toBe(TeamInvitationStatus::Pending)
        ->and($memberMembership->fresh()->ended_at)->toBeNull();
});

test('a deactivated team rejects the complete hiring and assessment mutation matrix', function () {
    $team = Team::factory()->deactivated()->create();
    $campaign = Campaign::factory()->for($team)->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();
    $assessment = Assessment::factory()->for($campaign)->create();
    $member = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();

    $mutations = [
        ['POST', route('admin.campaigns.store')],
        ['PATCH', route('admin.campaigns.update', $campaign)],
        ['DELETE', route('admin.campaigns.destroy', $campaign)],
        ['POST', route('admin.campaigns.publish', $campaign)],
        ['POST', route('admin.campaigns.archive', $campaign)],
        ['POST', route('admin.campaigns.draft', $campaign)],
        ['PATCH', route('admin.campaigns.ranking.update', $campaign)],
        ['POST', route('admin.campaigns.invitations.store', $campaign)],
        ['POST', route('admin.campaigns.generate-assessment', $campaign)],
        ['POST', route('admin.campaigns.sections.store', $campaign)],
        ['DELETE', route('admin.campaigns.sections.destroy', [$campaign, $section])],
        ['POST', route('admin.campaigns.questions.store', $campaign)],
        ['POST', route('admin.campaigns.questions.approve-all', $campaign)],
        ['POST', route('admin.campaigns.questions.approve', [$campaign, $question])],
        ['POST', route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question])],
        ['POST', route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question])],
        ['PATCH', route('admin.campaigns.questions.update', [$campaign, $question])],
        ['DELETE', route('admin.campaigns.questions.destroy', [$campaign, $question])],
        ['POST', route('admin.assessments.retry-evaluation', $assessment)],
        ['POST', route('admin.assessments.retry-email', $assessment)],
        ['POST', route('admin.assessments.promote', $assessment)],
        ['POST', route('admin.assessments.override-score', $assessment)],
        ['POST', route('admin.assessments.approve', $assessment)],
        ['POST', route('admin.assessments.reject', $assessment)],
    ];

    foreach ($mutations as [$method, $url]) {
        $this->actingAs($member)->call($method, $url)->assertForbidden();
    }
});

test('a deactivated team rejects every candidate Exam Session mutation', function () {
    $team = Team::factory()->create();
    $campaign = Campaign::factory()->for($team)->active()->create();
    $candidate = User::factory()->candidate()->create();
    CampaignInvitation::factory()->for($campaign)->accepted($candidate)->create();
    $examSession = ExamSession::factory()->for($campaign)->for($candidate)->create();
    $team->update(['status' => TeamStatus::Deactivated, 'deactivated_at' => now()]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertNotFound();
    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $examSession]), ['answers' => ['1' => 'answer']])
        ->assertNotFound();
    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $examSession]))
        ->assertNotFound();
    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.violations.store', [$campaign, $examSession]), ['type' => 'window_blur'])
        ->assertNotFound();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.finalize', [$campaign, $examSession]), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
        ])
        ->assertNotFound();

    expect(fn () => app(ExamSessionService::class)->startSession($candidate, $campaign))
        ->toThrow(ValidationException::class);
    expect($examSession->fresh()->status)->toBe(ExamSessionStatus::InProgress);
});
