<?php

use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can retry failed evaluation without creating a duplicate assessment', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::EvaluationFailed,
            'assessment_score' => 42,
            'ai_justification' => 'Stale failed output.',
            'ranking_score' => 42,
            'ranking_payload' => ['stale' => true],
            'critic_payload' => ['outcome' => 'failed'],
            'evaluated_at' => now(),
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.retry-evaluation', $assessment), [
            'reason' => 'Qwen timed out on the first attempt.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Submitted)
        ->assessment_score->toBeNull()
        ->ai_justification->toBeNull()
        ->ranking_score->toBeNull()
        ->ranking_payload->toBeNull()
        ->critic_payload->toBeNull()
        ->evaluated_at->toBeNull()
        ->and(Assessment::query()->whereBelongsTo($assessment->user)->count())->toBe(1)
        ->and($assessment->events()->where('type', 'admin_retried_evaluation')->first()?->payload)
        ->toMatchArray([
            'reason' => 'Qwen timed out on the first attempt.',
            'from_status' => AssessmentStatus::EvaluationFailed->value,
            'to_status' => AssessmentStatus::Submitted->value,
        ]);

    Queue::assertPushed(EvaluateAssessmentWithAi::class, fn (EvaluateAssessmentWithAi $job) => $job->assessment->is($assessment));
});

test('retry evaluation is rejected for non failed statuses', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.retry-evaluation', $assessment))
        ->assertSessionHasErrors('assessment')
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh()->status)->toBe(AssessmentStatus::Evaluated);
    Queue::assertNothingPushed();
});

test('admin can retry failed interview email delivery', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::EmailFailed,
            'approved_email_subject' => 'Interview invitation',
            'approved_email_body' => 'Please join the next stage.',
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.retry-email', $assessment), [
            'reason' => 'Resend after mail transport recovery.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Approved)
        ->and($assessment->events()->where('type', 'admin_retried_email')->first()?->payload)
        ->toMatchArray([
            'reason' => 'Resend after mail transport recovery.',
            'from_status' => AssessmentStatus::EmailFailed->value,
            'to_status' => AssessmentStatus::Approved->value,
        ]);

    Queue::assertPushed(SendInterviewInvitationEmail::class, fn (SendInterviewInvitationEmail $job) => $job->assessment->is($assessment));
});

test('retry email is rejected for non failed email statuses', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::EmailSent,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.retry-email', $assessment))
        ->assertSessionHasErrors('assessment')
        ->assertRedirect(route('admin.assessments.show', $assessment));

    Queue::assertNothingPushed();
});

test('admin can promote a false negative with manual email draft', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
            'ai_email_subject' => null,
            'ai_email_body' => null,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.promote', $assessment), [
            'reason' => 'Strong project evidence offsets the low AI score.',
            'email_subject' => 'Interview invitation',
            'email_body' => 'Please continue to the interview stage.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_email_subject->toBe('Interview invitation')
        ->ai_email_body->toBe('Please continue to the interview stage.')
        ->and($assessment->events()->where('type', 'admin_promoted')->first()?->payload)
        ->toMatchArray([
            'reason' => 'Strong project evidence offsets the low AI score.',
            'manual_email_supplied' => true,
        ]);
});

test('promote requires manual email draft when no ai draft exists', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
            'ai_email_subject' => null,
            'ai_email_body' => null,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.promote', $assessment), [
            'reason' => 'Promote despite low score.',
        ])
        ->assertSessionHasErrors(['email_subject', 'email_body'])
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh()->status)->toBe(AssessmentStatus::Evaluated);
});

test('admin can promote needs manual review assessment to pending approval', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::NeedsManualReview,
            'needs_manual_review' => true,
            'ai_email_subject' => 'Interview Invitation',
            'ai_email_body' => 'We would like to invite you to continue to the interview stage.',
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.promote', $assessment), [
            'reason' => 'Manual review cleared after HRD follow-up.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_email_subject->toBe('Interview Invitation')
        ->and($assessment->events()->where('type', 'admin_promoted')->first()?->payload)
        ->toMatchArray([
            'reason' => 'Manual review cleared after HRD follow-up.',
            'from_status' => AssessmentStatus::NeedsManualReview->value,
            'to_status' => AssessmentStatus::PendingApproval->value,
            'manual_email_supplied' => false,
        ]);
});

test('admin can promote using existing ai email draft without manual email fields', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
            'ai_email_subject' => 'Interview Invitation - Backend',
            'ai_email_body' => 'Thank you for completing the assessment.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.assessments.promote', $assessment), [
            'reason' => 'Strong portfolio evidence supports interview.',
        ])
        ->assertSessionHasNoErrors();

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_email_subject->toBe('Interview Invitation - Backend')
        ->ai_email_body->toBe('Thank you for completing the assessment.');
});

test('admin can override ranking score with reason', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
            'ranking_score' => 62,
            'ranking_payload' => [
                'formula' => 'resume_score * 0.35 + assessment_score * 0.65',
            ],
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.override-score', $assessment), [
            'ranking_score' => 88,
            'reason' => 'Manual rubric review found stronger evidence.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Overridden)
        ->ranking_score->toBe(88)
        ->and(data_get($assessment->ranking_payload, 'override.from_score'))->toBe(62)
        ->and(data_get($assessment->ranking_payload, 'override.to_score'))->toBe(88)
        ->and(data_get($assessment->ranking_payload, 'override.reason'))->toBe('Manual rubric review found stronger evidence.')
        ->and(data_get($assessment->ranking_payload, 'override.actor_id'))->toBe($admin->id)
        ->and($assessment->events()->where('type', 'admin_overrode_ranking_score')->first()?->payload)
        ->toMatchArray([
            'from_score' => 62,
            'to_score' => 88,
            'reason' => 'Manual rubric review found stronger evidence.',
        ]);
});

test('override score requires a reason', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
            'ranking_score' => 62,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.override-score', $assessment), [
            'ranking_score' => 88,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason')
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Evaluated)
        ->ranking_score->toBe(62);
});

test('admin reject requires and records a reason', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.reject', $assessment), [
            'reason' => 'Candidate did not meet minimum rubric expectations.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Rejected)
        ->rejected_at->not->toBeNull()
        ->and($assessment->events()->where('type', 'admin_rejected')->first()?->payload)
        ->toMatchArray([
            'reason' => 'Candidate did not meet minimum rubric expectations.',
        ]);
});

test('candidate cannot run recovery or override actions', function (string $route) {
    $candidate = User::factory()->create();
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'status' => AssessmentStatus::EvaluationFailed,
        ]);

    $this->actingAs($candidate)
        ->post(route($route, $assessment), [
            'ranking_score' => 88,
            'reason' => 'Candidate attempt.',
            'email_subject' => 'Subject',
            'email_body' => 'Body',
        ])
        ->assertForbidden();
})->with([
    'admin.assessments.retry-evaluation',
    'admin.assessments.retry-email',
    'admin.assessments.promote',
    'admin.assessments.override-score',
    'admin.assessments.reject',
]);
