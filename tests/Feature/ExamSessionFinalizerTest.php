<?php

use App\AssessmentStatus;
use App\ExamSessionStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionStatus;
use App\Services\ExamSessionFinalizer;
use App\Services\ExamSessionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('exam session finalizer creates the assessment and queues processing', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Explain dependency injection.',
        ]);
    $sessions = app(ExamSessionService::class);
    $session = $sessions->startSession($candidate, $campaign);

    $session = $sessions->saveCurrentSectionAnswers($session, $campaign, [
        $question->id => 'Collaborators are supplied from outside the class.',
    ]);
    $sessions->advanceSection($session, $campaign);

    $assessment = app(ExamSessionFinalizer::class)->finalize(
        session: $session->fresh(),
        campaign: $campaign,
        resume: resumePdfUpload(),
    );

    expect($assessment)
        ->toBeInstanceOf(Assessment::class)
        ->campaign_id->toBe($campaign->id)
        ->user_id->toBe($candidate->id)
        ->status->toBe(AssessmentStatus::Submitted)
        ->resume_original_name->toBe('resume.pdf')
        ->answers_payload->toHaveCount(1)
        ->and($assessment->answers_payload[0])
        ->toMatchArray([
            'question_id' => $question->id,
            'campaign_section_id' => $section->id,
            'section_title' => 'Knowledge Check',
            'question' => 'Explain dependency injection.',
            'answer' => 'Collaborators are supplied from outside the class.',
        ])
        ->and($session->fresh())
        ->status->toBe(ExamSessionStatus::Finalized)
        ->assessment_id->toBe($assessment->id)
        ->submission_reason->toBe('candidate_submitted')
        ->finalized_at->not->toBeNull()
        ->and($assessment->events()->pluck('type')->all())
        ->toBe([
            'candidate_submitted',
            'resume_uploaded',
            'assessment_queued',
        ]);

    Storage::disk('local')->assertExists($assessment->resume_path);
    Bus::assertChained([
        fn (ScreenResumeWithAi $job): bool => $job->afterCommit === true,
        EvaluateAssessmentWithAi::class,
    ]);

    $replayed = app(ExamSessionFinalizer::class)->finalize(
        session: $session->fresh(),
        campaign: $campaign,
    );

    expect($replayed->is($assessment))->toBeTrue()
        ->and($assessment->events()->where('type', 'assessment_queued')->count())->toBe(1);
});

test('exam session finalizer can force submit incomplete answers', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $answeredQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Answered question',
        ]);
    $unansweredQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Unanswered question',
        ]);
    $session = app(ExamSessionService::class)->startSession($candidate, $campaign);
    $session->update([
        'answer_drafts' => [
            (string) $answeredQuestion->id => 'Only one answer.',
        ],
        'resume_path' => 'resumes/preuploaded.pdf',
        'resume_original_name' => 'preuploaded.pdf',
        'warning_count' => 3,
        'integrity_events' => [
            ['type' => 'tab_hidden', 'occurred_at' => now()->toIso8601String()],
        ],
    ]);

    $assessment = app(ExamSessionFinalizer::class)->finalize(
        session: $session->fresh(),
        campaign: $campaign,
        submissionReason: 'integrity_max_warnings',
        status: ExamSessionStatus::AutoSubmitted,
        allowIncompleteAnswers: true,
    );

    expect($assessment->answers_payload)
        ->toHaveCount(2)
        ->and(collect($assessment->answers_payload)->firstWhere('question_id', $answeredQuestion->id)['answer'])
        ->toBe('Only one answer.')
        ->and(collect($assessment->answers_payload)->firstWhere('question_id', $unansweredQuestion->id)['answer'])
        ->toBe('')
        ->and($session->fresh())
        ->status->toBe(ExamSessionStatus::AutoSubmitted)
        ->submission_reason->toBe('integrity_max_warnings')
        ->and($assessment->events()->pluck('type')->all())
        ->toBe([
            'candidate_submitted',
            'resume_uploaded',
            'exam_integrity_summary',
            'assessment_queued',
        ]);

    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);
});

test('exam session finalizer auto-submits expired incomplete sessions without a resume', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
    ]);
    $answeredQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Answered question',
        ]);
    $unansweredQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Unanswered question',
        ]);
    $session = app(ExamSessionService::class)->startSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [
            (string) $answeredQuestion->id => 'Only one answer.',
        ],
    ]);

    $assessment = app(ExamSessionFinalizer::class)->finalize(
        session: $session->fresh(),
        campaign: $campaign,
        submissionReason: 'section_timer_expired',
        status: ExamSessionStatus::AutoSubmitted,
        allowIncompleteAnswers: true,
    );

    expect($assessment)
        ->resume_path->toBeNull()
        ->and(collect($assessment->answers_payload)->firstWhere('question_id', $answeredQuestion->id)['answer'])
        ->toBe('Only one answer.')
        ->and(collect($assessment->answers_payload)->firstWhere('question_id', $unansweredQuestion->id)['answer'])
        ->toBe('')
        ->and($session->fresh())
        ->status->toBe(ExamSessionStatus::AutoSubmitted)
        ->submission_reason->toBe('section_timer_expired')
        ->and($assessment->events()->pluck('type')->all())
        ->toBe([
            'candidate_submitted',
            'assessment_queued',
        ])
        ->and($assessment->events()->where('type', 'candidate_submitted')->value('payload'))
        ->toMatchArray(['resume_uploaded' => false]);

    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);
});

test('exam session finalizer still requires a resume for manual submissions', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);
    $sessions = app(ExamSessionService::class);
    $session = $sessions->startSession($candidate, $campaign);
    $session = $sessions->saveCurrentSectionAnswers($session, $campaign, [
        $question->id => 'Complete answer.',
    ]);
    $sessions->advanceSection($session, $campaign);

    expect(fn () => app(ExamSessionFinalizer::class)->finalize(
        session: $session->fresh(),
        campaign: $campaign,
    ))->toThrow(ValidationException::class);
});
