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
