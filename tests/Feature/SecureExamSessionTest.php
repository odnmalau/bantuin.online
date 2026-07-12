<?php

use App\AssessmentStatus;
use App\ExamSessionStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\ExamSession;
use App\Models\User;
use App\QuestionStatus;
use App\Services\ExamSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

test('candidate must start a secure exam session before seeing section questions', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 30,
    ]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('candidate/exam')
            ->where('state', 'ready_to_start')
            ->where('secure_exam.require_fullscreen', true)
            ->where('secure_exam.block_copy_paste', true)
            ->missing('examSession')
            ->missing('currentSection')
            ->missing('questions')
            ->has('sections', 1),
        );
});

test('candidate can start an exam session and receive the first section timer', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 15,
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Secure section question',
        ]);

    $session = startCandidateExamSession($candidate, $campaign);

    expect($session)
        ->status->toBe(ExamSessionStatus::InProgress)
        ->current_section_id->toBe($section->id)
        ->current_section_expires_at->not->toBeNull();

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('candidate/exam')
            ->where('state', 'active_section')
            ->where('examSession.id', $session->id)
            ->where('currentSection.id', $section->id)
            ->where('questions.0.id', $question->id)
            ->where('questions.0.content', 'Secure section question'),
        );
});

test('candidate cannot advance a section without answering every question', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session]))
        ->assertSessionHasErrors("answers.{$question->id}");
});

test('candidate cannot save an exam answer that exceeds the configured max length', function () {
    config()->set('assessment.secure_exam.max_answer_characters', 100);

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => [
                $question->id => str_repeat('a', 101),
            ],
        ])
        ->assertSessionHasErrors("answers.{$question->id}");
});

test('incomplete section timer expiry auto-finalizes the exam session', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
    ]);
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [],
    ]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session]))
        ->assertRedirect();

    $session = $session->fresh();
    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    expect($session)
        ->status->toBe(ExamSessionStatus::AutoSubmitted)
        ->submission_reason->toBe('section_timer_expired')
        ->assessment_id->toBe($assessment->id)
        ->and($assessment->resume_path)->toBeNull()
        ->and($assessment->events()->where('type', 'candidate_submitted')->value('payload'))
        ->toMatchArray(['resume_uploaded' => false]);
});

test('exam page loads after incomplete section timer expiry', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
    ]);
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [],
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('candidate/exam')
            ->where('state', 'submitted')
            ->has('assessment.id')
            ->missing('examSession'),
        );

    expect($session->fresh())
        ->status->toBe(ExamSessionStatus::AutoSubmitted)
        ->submission_reason->toBe('section_timer_expired');
});

test('complete section timer expiry advances only to the next section', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $firstSection = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
        'sort_order' => 1,
    ]);
    $secondSection = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 10,
        'sort_order' => 2,
    ]);
    $firstQuestion = CampaignQuestion::factory()->for($campaign)->for($firstSection, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    CampaignQuestion::factory()->for($campaign)->for($secondSection, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [
            (string) $firstQuestion->id => 'Answered before expiry.',
        ],
    ]);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session]))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign))
        ->assertSessionHasNoErrors();

    $session = $session->fresh();

    expect($session)
        ->status->toBe(ExamSessionStatus::InProgress)
        ->current_section_id->toBe($secondSection->id)
        ->completed_section_ids->toBe([$firstSection->id])
        ->and(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
});

test('section expiry uses fresh locked answers instead of a stale session model', function () {
    Bus::fake();

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
    ]);
    $question = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    $staleSession = startCandidateExamSession($candidate, $campaign);

    ExamSession::query()->whereKey($staleSession->id)->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [
            (string) $question->id => 'Completed before expiry.',
        ],
    ]);

    $result = app(ExamSessionService::class)->syncSectionExpiry($staleSession, $campaign);

    expect($result)
        ->toMatchArray([
            'advanced' => true,
            'finalized' => false,
            'assessment' => null,
        ])
        ->and($staleSession->fresh())
        ->status->toBe(ExamSessionStatus::InProgress)
        ->current_section_id->toBeNull()
        ->completed_section_ids->toBe([$section->id])
        ->and(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
});

test('duplicate stale expiry requests do not reset the next section timer', function () {
    $this->travelTo(Carbon::parse('2026-07-13 10:00:00'));

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $firstSection = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
        'sort_order' => 1,
    ]);
    $secondSection = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 10,
        'sort_order' => 2,
    ]);
    $firstQuestion = CampaignQuestion::factory()->for($campaign)->for($firstSection, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    CampaignQuestion::factory()->for($campaign)->for($secondSection, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    $session = startCandidateExamSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [
            (string) $firstQuestion->id => 'Completed before expiry.',
        ],
    ]);
    $firstRequestSession = $session->fresh();
    $duplicateRequestSession = $session->fresh();
    $sessions = app(ExamSessionService::class);

    $firstResult = $sessions->syncSectionExpiry($firstRequestSession, $campaign);
    $advancedSession = $session->fresh();
    $nextSectionStartedAt = $advancedSession->current_section_started_at;
    $nextSectionExpiresAt = $advancedSession->current_section_expires_at;

    $this->travel(2)->minutes();
    $duplicateResult = $sessions->syncSectionExpiry($duplicateRequestSession, $campaign);
    $afterDuplicate = $session->fresh();

    expect($firstResult['advanced'])->toBeTrue()
        ->and($duplicateResult)
        ->toMatchArray([
            'advanced' => true,
            'finalized' => false,
            'assessment' => null,
        ])
        ->and($afterDuplicate)
        ->current_section_id->toBe($secondSection->id)
        ->completed_section_ids->toBe([$firstSection->id])
        ->and($afterDuplicate->current_section_started_at->equalTo($nextSectionStartedAt))->toBeTrue()
        ->and($afterDuplicate->current_section_expires_at->equalTo($nextSectionExpiresAt))->toBeTrue();
});

test('integrity violations increment warning count on the session', function () {
    config()->set('assessment.secure_exam.max_integrity_warnings', 5);

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.violations.store', [$campaign, $session]), [
            'type' => 'tab_hidden',
        ])
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    expect($session->fresh()->warning_count)->toBe(1);
});

test('sequential draft saves merge answers under the locked session row', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $firstQuestion = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
        'sort_order' => 1,
    ]);
    $secondQuestion = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
        'sort_order' => 2,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => [
                $firstQuestion->id => 'First answer.',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session->fresh()]), [
            'answers' => [
                $secondQuestion->id => 'Second answer.',
            ],
        ])
        ->assertRedirect();

    expect($session->fresh()->answer_drafts)->toMatchArray([
        (string) $firstQuestion->id => 'First answer.',
        (string) $secondQuestion->id => 'Second answer.',
    ]);
});

test('reaching max integrity warnings auto-submits once', function () {
    Bus::fake();
    Storage::fake('local');
    config()->set('assessment.secure_exam.max_integrity_warnings', 2);
    config()->set('assessment.secure_exam.auto_submit_on_max_warnings', true);

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.violations.store', [$campaign, $session]), [
            'type' => 'tab_hidden',
        ])
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    expect($session->fresh()->warning_count)->toBe(1);

    $response = $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.violations.store', [$campaign, $session->fresh()]), [
            'type' => 'window_blur',
        ]);

    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    $response->assertRedirect(route('candidate.assessments.show', $assessment));

    expect($session->fresh())
        ->status->toBe(ExamSessionStatus::AutoSubmitted)
        ->warning_count->toBe(2)
        ->submission_reason->toBe('integrity_max_warnings')
        ->assessment_id->toBe($assessment->id)
        ->and(Assessment::query()->whereBelongsTo($candidate)->count())->toBe(1);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.violations.store', [$campaign, $session->fresh()]), [
            'type' => 'tab_hidden',
        ])
        ->assertSessionHasErrors('session');

    expect(Assessment::query()->whereBelongsTo($candidate)->count())->toBe(1);
});

test('candidate can finalize a secure exam session into an assessment', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'prompt' => 'Finalize me',
        ]);

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => [
                $question->id => 'Final answer.',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session->fresh()]))
        ->assertRedirect();

    $session = $session->fresh();
    expect($session->current_section_id)->toBeNull();

    $response = $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.finalize', [$campaign, $session->fresh()]), [
            'resume' => resumePdfUpload(),
        ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    $response->assertRedirect(route('candidate.assessments.show', $assessment));

    expect($assessment)
        ->campaign_id->toBe($campaign->id)
        ->status->toBe(AssessmentStatus::Submitted)
        ->answers_payload->toHaveCount(1)
        ->and($session->fresh())
        ->status->toBe(ExamSessionStatus::Finalized)
        ->assessment_id->toBe($assessment->id);

    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);
});

test('source contract keeps fullscreen entry on start click and exit reporting in proctoring', function () {
    $examSource = file_get_contents(resource_path('js/pages/candidate/exam.tsx'));
    $proctoringSource = file_get_contents(resource_path('js/hooks/use-exam-proctoring.ts'));

    $startOffset = strpos($examSource, 'function StartExamState');
    $activeOffset = strpos($examSource, 'function ActiveSectionExam');

    expect($startOffset)->not->toBeFalse()
        ->and($activeOffset)->not->toBeFalse()
        ->and($activeOffset)->toBeGreaterThan($startOffset);

    $startExamState = substr($examSource, $startOffset, $activeOffset - $startOffset);

    expect($startExamState)
        ->toContain('requestFullscreen')
        ->toContain('ExamSessionController.store.url')
        ->toContain('router.post')
        ->and(strpos($startExamState, 'requestFullscreen'))
        ->toBeLessThan(strpos($startExamState, 'router.post'))
        ->and($proctoringSource)
        ->not->toContain('requestFullscreen')
        ->toContain('fullscreenchange')
        ->toContain("report('fullscreen_exit')");
});
