<?php

use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\AssessmentEvent;
use App\Models\Campaign;
use App\Models\User;
use App\Services\AssessmentEventRecorder;
use App\Services\AssessmentWorkflowService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
});

test('first committed decision wins when stale instances race approve then reject', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'ai_email_subject' => 'AI subject',
            'ai_email_body' => 'AI body',
        ]);

    $staleForApprove = Assessment::query()->findOrFail($assessment->id);
    $staleForReject = Assessment::query()->findOrFail($assessment->id);

    $workflow = app(AssessmentWorkflowService::class);

    $workflow->approve($staleForApprove, $admin, [
        'email_subject' => 'Final subject',
        'email_body' => 'Final body.',
    ]);

    expect(fn () => $workflow->reject($staleForReject, $admin, [
        'reason' => 'Late reject after approve.',
    ]))->toThrow(ValidationException::class);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Approved)
        ->rejected_at->toBeNull()
        ->and($assessment->events()->where('type', 'admin_approved')->count())->toBe(1)
        ->and($assessment->events()->where('type', 'admin_rejected')->count())->toBe(0);

    Queue::assertPushed(SendInterviewInvitationEmail::class, 1);
});

test('approve creates one event and queues one email job', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::Evaluated,
        ]);

    app(AssessmentWorkflowService::class)->approve($assessment, $admin, [
        'email_subject' => 'Interview invitation',
        'email_body' => 'Please join the next stage.',
    ]);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Approved)
        ->and($assessment->events()->where('type', 'admin_approved')->count())->toBe(1);

    Queue::assertPushed(SendInterviewInvitationEmail::class, 1);
});

test('duplicate email job after email sent is a no-op', function () {
    Mail::fake();

    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory())
        ->create([
            'status' => AssessmentStatus::EmailSent,
            'email_sent_at' => now(),
        ]);

    $eventsBefore = $assessment->events()->count();

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertNothingSent();
    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailSent)
        ->and($assessment->events()->count())->toBe($eventsBefore);
});

test('email job after rejected is a no-op', function () {
    Mail::fake();

    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'status' => AssessmentStatus::Rejected,
            'rejected_at' => now(),
            'approved_email_subject' => 'Stale subject',
            'approved_email_body' => 'Stale body.',
        ]);

    $eventsBefore = $assessment->events()->count();

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertNothingSent();
    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Rejected)
        ->and($assessment->events()->count())->toBe($eventsBefore);
});

test('approved with missing recipient subject or body becomes email failed', function () {
    Mail::fake();

    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory())
        ->create([
            'approved_email_subject' => null,
            'approved_email_body' => null,
        ]);

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertNothingSent();
    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailFailed)
        ->email_sent_at->toBeNull()
        ->and($assessment->events()->where('type', 'email_failed')->count())->toBe(1);
});

test('evaluation job from any status except submitted is a no-op', function (AssessmentStatus $status) {
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'status' => $status,
            'ai_score' => 70,
            'ai_justification' => 'Existing evaluation.',
            'evaluated_at' => now(),
        ]);

    $eventsBefore = $assessment->events()->count();

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    expect($assessment->refresh())
        ->status->toBe($status)
        ->ai_score->toBe(70)
        ->ai_justification->toBe('Existing evaluation.')
        ->and($assessment->events()->count())->toBe($eventsBefore);
})->with([
    AssessmentStatus::Evaluating,
    AssessmentStatus::PendingApproval,
    AssessmentStatus::Evaluated,
    AssessmentStatus::Approved,
    AssessmentStatus::EmailSent,
    AssessmentStatus::Rejected,
    AssessmentStatus::EvaluationFailed,
]);

test('retry evaluation from evaluation failed resets and queues once', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::EvaluationFailed,
            'ai_score' => 42,
            'ai_justification' => 'Stale failed output.',
            'evaluated_at' => now(),
        ]);

    app(AssessmentWorkflowService::class)->retryEvaluation($assessment, $admin, [
        'reason' => 'Retry after provider outage.',
    ]);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Submitted)
        ->ai_score->toBeNull()
        ->ai_justification->toBeNull()
        ->evaluated_at->toBeNull()
        ->and($assessment->events()->where('type', 'admin_retried_evaluation')->count())->toBe(1);

    Queue::assertPushed(EvaluateAssessmentWithAi::class, 1);
});

test('accepted transitions write exactly one event and roll back when recording fails', function () {
    Queue::fake();

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
        ]);

    $this->app->instance(AssessmentEventRecorder::class, new class extends AssessmentEventRecorder
    {
        public function record(
            Assessment $assessment,
            string $type,
            string $title,
            ?string $description = null,
            array $payload = [],
            ?User $actor = null,
        ): AssessmentEvent {
            throw new RuntimeException('Forced event recorder failure.');
        }
    });

    expect(fn () => app(AssessmentWorkflowService::class)->approve($assessment, $admin, [
        'email_subject' => 'Interview invitation',
        'email_body' => 'Please join the next stage.',
    ]))->toThrow(RuntimeException::class);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->approved_at->toBeNull()
        ->and($assessment->events()->count())->toBe(0);

    Queue::assertNothingPushed();
});
