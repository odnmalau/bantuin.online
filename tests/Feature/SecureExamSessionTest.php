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
    $candidate = User::factory()->candidate()->create();
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
            ->where('examSession', null)
            ->where('currentSection', null)
            ->has('questions', 0)
            ->has('sections', 1),
        );
});

test('candidate can start an exam session and receive the first section timer', function () {
    $candidate = User::factory()->candidate()->create();
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
            ->where('examSession.id', $session->id)
            ->where('currentSection.id', $section->id)
            ->where('questions.0.id', $question->id)
            ->where('questions.0.content', 'Secure section question'),
        );
});

test('candidate cannot advance a section without answering every question', function () {
    $candidate = User::factory()->candidate()->create();
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

test('candidate cannot advance after the section timer expires', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'duration_minutes' => 5,
    ]);
    $question = CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);

    $session = startCandidateExamSession($candidate, $campaign);
    $session->update([
        'current_section_expires_at' => now()->subMinute(),
        'answer_drafts' => [
            (string) $question->id => 'Answered before expiry.',
        ],
    ]);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session]))
        ->assertSessionHasErrors('section');
});

test('integrity violations increment warning count on the session', function () {
    config()->set('assessment.secure_exam.max_integrity_warnings', 5);

    $candidate = User::factory()->candidate()->create();
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

    $candidate = User::factory()->candidate()->create();
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
