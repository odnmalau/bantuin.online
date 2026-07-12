<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Mail\InterviewInvitationMail;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Ai\QwenResumeScreener;
use App\Services\Ai\ResumeScreeningResult;
use App\Services\AssessmentEvaluationPipeline;
use App\Services\AssessmentExternalWorkCoordinator;
use App\Services\ClaimedAssessmentWork;
use App\Services\ResumeTextExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->withoutVite();
});

test('resume screening extractor and screener do not open nested transactions', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $assessment = Assessment::factory()
        ->for($candidate)
        ->for($campaign)
        ->create([
            'resume_path' => 'resumes/resume.pdf',
            'status' => AssessmentStatus::Submitted,
        ]);

    $baselineLevel = DB::transactionLevel();
    $extractorSeenLevel = null;
    $screenerSeenLevel = null;

    $extractor = Mockery::mock(ResumeTextExtractor::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->andReturnUsing(function () use (&$extractorSeenLevel): string {
            $extractorSeenLevel = DB::transactionLevel();

            return 'Laravel experience';
        });

    $screener = Mockery::mock(QwenResumeScreener::class);
    $screener->shouldReceive('screen')
        ->once()
        ->andReturnUsing(function () use (&$screenerSeenLevel): ResumeScreeningResult {
            $screenerSeenLevel = DB::transactionLevel();

            return new ResumeScreeningResult(
                score: 80,
                summary: 'Backend experience.',
                matchedSkills: ['Laravel'],
                missingSkills: [],
                riskFlags: [],
                interviewProbes: [],
                confidence: 80,
                justification: 'Strong Laravel evidence.',
            );
        });

    app()->instance(ResumeTextExtractor::class, $extractor);
    app()->instance(QwenResumeScreener::class, $screener);

    app()->call([(new ScreenResumeWithAi($assessment)), 'handle']);

    expect($extractorSeenLevel)->toBe($baselineLevel)
        ->and($screenerSeenLevel)->toBe($baselineLevel)
        ->and($assessment->refresh()->status)->toBe(AssessmentStatus::Submitted)
        ->and($assessment->resume_screening_attempt_id)->toBeNull();
});

test('stale resume screening attempt cannot overwrite a newer claim', function () {
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'resume_path' => 'resumes/resume.pdf',
            'status' => AssessmentStatus::Submitted,
        ]);

    $teamId = $assessment->campaign()->value('team_id');
    expect($teamId)->not->toBeNull()
        ->and($assessment->resume_path)->toBe('resumes/resume.pdf')
        ->and($assessment->status)->toBe(AssessmentStatus::Submitted);

    $coordinator = app(AssessmentExternalWorkCoordinator::class);
    $first = $coordinator->claimResumeScreening($assessment, (int) $teamId);

    expect($first)->not->toBeNull();

    Assessment::query()->whereKey($assessment->id)->update([
        'status' => AssessmentStatus::Submitted,
        'resume_screening_attempt_id' => null,
        'resume_screening_started_at' => null,
    ]);
    $second = $coordinator->claimResumeScreening($assessment->fresh(), (int) $teamId);

    expect($second)->not->toBeNull();
    expect($coordinator->finalizeResumeScreening(
        assessment: $assessment->fresh(),
        attemptId: $first->attemptId,
        attributes: [
            'resume_text' => 'stale',
            'status' => AssessmentStatus::Submitted,
        ],
        events: [],
    ))->toBeFalse();
    expect($coordinator->finalizeResumeScreening(
        assessment: $assessment->fresh(),
        attemptId: $second->attemptId,
        attributes: [
            'resume_text' => 'fresh',
            'resume_score' => 90,
            'status' => AssessmentStatus::Submitted,
        ],
        events: [],
    ))->toBeTrue();
    expect($assessment->refresh()->resume_text)->toBe('fresh');
});

test('evaluation compute runs outside transactions and stale attempts are ignored', function () {
    config()->set('ai.providers.qwen.key', 'test-qwen-key');

    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'Strong answer.',
            'email' => [
                'subject' => 'Interview Invitation',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
    ]);
    AssessmentCriticAgent::fake([
        [
            'outcome' => 'passed',
            'summary' => 'Consistent.',
            'findings' => [],
            'manual_review_required' => false,
            'repaired_email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'status' => AssessmentStatus::Submitted,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions tradeoffs.',
                    'answer' => 'Indexes help reads.',
                ],
            ],
        ]);

    $coordinator = app(AssessmentExternalWorkCoordinator::class);
    $claimed = $coordinator->claimEvaluation($assessment, $assessment->campaign->team_id);
    expect($claimed)->not->toBeNull();

    $pipeline = app(AssessmentEvaluationPipeline::class);
    $baselineLevel = DB::transactionLevel();
    $outcome = $pipeline->compute($claimed->assessment);
    expect(DB::transactionLevel())->toBe($baselineLevel);

    $stale = $coordinator->finalizeEvaluation($assessment->fresh(), 'not-the-attempt', $outcome);
    expect($stale)->toBeNull()
        ->and($assessment->refresh()->status)->toBe(AssessmentStatus::Evaluating);

    $applied = $coordinator->finalizeEvaluation($assessment->fresh(), $claimed->attemptId, $outcome);
    expect($applied)->not->toBeNull()
        ->and($applied->status)->toBe(AssessmentStatus::PendingApproval)
        ->and($applied->evaluation_attempt_id)->toBeNull();
});

test('duplicate interview email jobs send once and post send finalize failure does not resend', function () {
    Mail::fake();

    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory())
        ->create();

    $first = new SendInterviewInvitationEmail($assessment);
    $second = new SendInterviewInvitationEmail($assessment);

    app()->call([$first, 'handle']);

    expect($assessment->refresh()->status)->toBe(AssessmentStatus::EmailSent);
    Mail::assertSent(InterviewInvitationMail::class, 1);

    app()->call([$second, 'handle']);
    Mail::assertSent(InterviewInvitationMail::class, 1);

    $assessment->update([
        'status' => AssessmentStatus::Approved,
        'email_sent_at' => null,
        'email_delivery_attempt_id' => null,
        'email_delivery_started_at' => null,
    ]);

    $coordinator = app(AssessmentExternalWorkCoordinator::class);
    $claimed = $coordinator->claimEmailDelivery($assessment->fresh(), $assessment->campaign->team_id);
    expect($claimed)->toBeInstanceOf(ClaimedAssessmentWork::class);

    Mail::to($claimed->assessment->user->email)->send(new InterviewInvitationMail(
        subjectLine: $claimed->assessment->approved_email_subject,
        body: $claimed->assessment->approved_email_body,
    ));

    expect($coordinator->finalizeEmailDelivery(
        assessment: $claimed->assessment,
        attemptId: 'wrong-attempt',
        attributes: [
            'status' => AssessmentStatus::EmailSent,
            'email_sent_at' => now(),
        ],
        events: [],
    ))->toBeFalse();

    expect($assessment->refresh()->status)->toBe(AssessmentStatus::EmailSending);

    app()->call([(new SendInterviewInvitationEmail($assessment->fresh())), 'handle']);

    Mail::assertSent(InterviewInvitationMail::class, 2);
    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailFailed)
        ->and($assessment->events()->where('type', 'email_failed')->latest('id')->first()?->payload)
        ->toMatchArray([
            'outcome' => 'unknown',
            'requires_manual_retry' => true,
        ]);
});

test('evaluation job timeout is driven by assessment queue budget config', function () {
    $assessment = Assessment::factory()->create([
        'status' => AssessmentStatus::Submitted,
    ]);

    $job = new EvaluateAssessmentWithAi($assessment);

    expect($job->timeout)->toBe((int) config('assessment.queue.evaluation_timeout'))
        ->and($job->timeout)->toBeGreaterThan(30);
});
