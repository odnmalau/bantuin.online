<?php

use App\AssessmentStatus;
use App\CampaignStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('candidate can view active campaign questions when assigned', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create([
        'title' => 'Backend Engineer Campaign',
        'role_title' => 'Backend Engineer',
    ]);
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
        'description' => 'Answer all questions in order before submitting.',
        'duration_minutes' => 30,
    ]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'prompt' => 'Draft question',
            'status' => QuestionStatus::Draft,
        ]);
    $activeQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'prompt' => 'Active campaign question',
            'status' => QuestionStatus::Approved,
            'type' => QuestionType::LongText,
            'points' => 20,
            'sort_order' => 2,
        ]);
    Campaign::factory()->create([
        'status' => CampaignStatus::Draft,
        'activated_at' => null,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('campaign.id', $campaign->id)
            ->has('sections', 1)
            ->where('sections.0.id', $section->id)
            ->where('sections.0.title', 'Knowledge Check')
            ->where('sections.0.description', 'Answer all questions in order before submitting.')
            ->where('sections.0.duration_minutes', 30)
            ->where('sections.0.question_count', 1)
            ->has('questions', 1)
            ->where('questions.0.id', $activeQuestion->id)
            ->where('questions.0.content', 'Active campaign question')
            ->where('questions.0.type', QuestionType::LongText->value)
            ->where('assessment', null),
        );
});

test('candidate exam entry redirects when only one assigned campaign is accessible', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $this->actingAs($candidate)
        ->get(route('candidate.exam'))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));
});

test('unassigned candidate cannot open a campaign exam', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertForbidden();
});

test('candidate sees submitted state only for the assigned campaign', function () {
    $candidate = User::factory()->candidate()->create();
    $previousCampaign = Campaign::factory()->create([
        'status' => CampaignStatus::Archived,
    ]);
    $activeCampaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $activeCampaign);
    $section = CampaignSection::factory()->for($activeCampaign)->create();
    CampaignQuestion::factory()->for($activeCampaign)->for($section, 'section')->create();
    Assessment::factory()
        ->for($candidate)
        ->for($previousCampaign)
        ->create();

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $activeCampaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('campaign.id', $activeCampaign->id)
            ->where('assessment', null),
        );
});

test('candidate can submit an assessment for an active campaign', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
        'weight' => 100,
    ]);
    $firstQuestion = CampaignQuestion::factory()
        ->multipleChoice()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'prompt' => 'Which datastore supports relational constraints?',
            'expected_rubric' => null,
            'options' => ['PostgreSQL', 'Redis'],
            'correct_answer' => ['PostgreSQL'],
            'points' => 10,
            'status' => QuestionStatus::Approved,
            'sort_order' => 1,
        ]);
    $secondQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::LongText,
            'prompt' => 'Explain dependency injection.',
            'expected_rubric' => 'Mentions inversion of control and testability.',
            'points' => 20,
            'status' => QuestionStatus::Approved,
            'sort_order' => 2,
        ]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Draft,
        ]);

    $response = $this->actingAs($candidate)
        ->post(route('candidate.campaigns.assessments.store', $campaign), [
            'resume' => resumePdfUpload(),
            'answers' => [
                $firstQuestion->id => 'PostgreSQL',
                $secondQuestion->id => 'Dependency injection passes collaborators from the outside.',
            ],
        ]);

    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    $response->assertRedirect(route('candidate.assessments.show', $assessment));

    expect($assessment)
        ->campaign_id->toBe($campaign->id)
        ->status->toBe(AssessmentStatus::Submitted)
        ->resume_path->not->toBeNull()
        ->resume_original_name->toBe('resume.pdf')
        ->answers_payload->toHaveCount(2)
        ->and($assessment->answers_payload[0])
        ->toMatchArray([
            'question_id' => $firstQuestion->id,
            'campaign_question_id' => $firstQuestion->id,
            'campaign_section_id' => $section->id,
            'section_title' => 'Knowledge Check',
            'section_weight' => 100,
            'question' => 'Which datastore supports relational constraints?',
            'type' => QuestionType::MultipleChoice->value,
            'grading_mode' => QuestionGradingMode::Deterministic->value,
            'correct_answer' => ['PostgreSQL'],
            'points' => 10,
            'answer' => 'PostgreSQL',
        ])
        ->and($assessment->answers_payload[1])
        ->toMatchArray([
            'question_id' => $secondQuestion->id,
            'question' => 'Explain dependency injection.',
            'rubric' => 'Mentions inversion of control and testability.',
            'type' => QuestionType::LongText->value,
            'grading_mode' => QuestionGradingMode::Ai->value,
            'answer' => 'Dependency injection passes collaborators from the outside.',
        ]);

    Storage::disk('local')->assertExists($assessment->resume_path);
    Bus::assertDispatched(ScreenResumeWithAi::class, fn (ScreenResumeWithAi $job): bool => $job->assessment->is($assessment));
    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);
});

test('candidate must answer every approved campaign question', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $answeredQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create();
    $missingQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create();

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.assessments.store', $campaign), [
            'resume' => resumePdfUpload(),
            'answers' => [
                $answeredQuestion->id => 'Answered.',
            ],
        ])
        ->assertSessionHasErrors("answers.{$missingQuestion->id}")
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    expect(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('candidate cannot submit more than one assessment for the same campaign', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create();
    Assessment::factory()
        ->for($candidate)
        ->for($campaign)
        ->create();

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.assessments.store', $campaign), [
            'resume' => resumePdfUpload(),
            'answers' => [
                $question->id => 'Second submission attempt.',
            ],
        ])
        ->assertSessionHasErrors('assessment')
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    Bus::assertNothingDispatched();
});

test('candidate can submit a different active campaign when assigned to both', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->candidate()->create();
    $previousCampaign = Campaign::factory()->active()->create([
        'activated_at' => now()->subDay(),
    ]);
    $activeCampaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $previousCampaign);
    assignCandidateToCampaignExam($candidate, $activeCampaign);
    $section = CampaignSection::factory()->for($activeCampaign)->create();
    $question = CampaignQuestion::factory()
        ->for($activeCampaign)
        ->for($section, 'section')
        ->create();
    Assessment::factory()
        ->for($candidate)
        ->for($previousCampaign)
        ->create();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.assessments.store', $activeCampaign), [
            'resume' => resumePdfUpload(),
            'answers' => [
                $question->id => 'New campaign answer.',
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(Assessment::query()->whereBelongsTo($candidate)->count())->toBe(2);
});

test('candidate cannot submit an inactive campaign', function () {
    Bus::fake();
    Storage::fake('local');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::QuestionReview,
        'activated_at' => null,
    ]);
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.assessments.store', $campaign), [
            'resume' => resumePdfUpload(),
            'answers' => [
                $question->id => 'Inactive campaign attempt.',
            ],
        ])
        ->assertForbidden();

    expect(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('candidate can view their own assessment', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    $assessment = Assessment::factory()->for($candidate)->for($campaign)->create();

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/assessments/show')
            ->where('assessment.id', $assessment->id)
            ->where('assessment.campaign.title', $campaign->title)
            ->where('assessment.status', AssessmentStatus::Submitted->value),
        );
});

test('candidate cannot view another candidates assessment', function () {
    $candidate = User::factory()->candidate()->create();
    $otherCandidate = User::factory()->candidate()->create();
    $assessment = Assessment::factory()->for($otherCandidate)->create();

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertForbidden();
});
