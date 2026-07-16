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
use App\QuestionStatus;
use App\QuestionType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('candidate can view active campaign questions when assigned', function () {
    $candidate = User::factory()->create();
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
            ->where('state', 'ready_to_start')
            ->where('campaign.id', $campaign->id)
            ->where('campaign.team.name', $campaign->team->name)
            ->has('sections', 1)
            ->where('sections.0.id', $section->id)
            ->where('sections.0.title', 'Knowledge Check')
            ->where('sections.0.description', 'Answer all questions in order before submitting.')
            ->where('sections.0.duration_minutes', 30)
            ->where('sections.0.question_count', 1)
            ->missing('examSession')
            ->missing('currentSection')
            ->missing('questions')
            ->missing('assessment'),
        );
});

test('candidate exam exposes an open ended question without its rubric', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::LongText,
            'prompt' => 'Explain when you would use a queue and a database index.',
            'expected_rubric' => 'Explains asynchronous work and read performance tradeoffs.',
            'status' => QuestionStatus::Approved,
        ]);

    startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('state', 'active_section')
            ->where('questions.0.id', $question->id)
            ->where('questions.0.type', QuestionType::LongText->value)
            ->where('questions.0.max_characters', QuestionType::LongText->maxCharacters())
            ->missing('questions.0.expected_rubric'),
        );
});

test('candidate exam entry redirects when only one assigned campaign is accessible', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $this->actingAs($candidate)
        ->get(route('candidate.exam'))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));
});

test('candidate exam entry shows empty state when no campaigns are accessible', function () {
    $candidate = User::factory()->create();

    $this->actingAs($candidate)
        ->get(route('candidate.exam'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('state', 'no_campaign')
            ->where('campaign', null)
            ->missing('campaigns'),
        );
});

test('candidate exam entry shows a campaign picker when multiple campaigns are accessible', function () {
    $candidate = User::factory()->create();
    $firstCampaign = Campaign::factory()->active()->create([
        'title' => 'Backend Engineer Campaign',
        'role_title' => 'Backend Engineer',
        'activated_at' => now()->subDay(),
    ]);
    $secondCampaign = Campaign::factory()->active()->create([
        'title' => 'Frontend Engineer Campaign',
        'role_title' => 'Frontend Engineer',
        'activated_at' => now(),
    ]);

    foreach ([$firstCampaign, $secondCampaign] as $campaign) {
        assignCandidateToCampaignExam($candidate, $campaign);
        $section = CampaignSection::factory()->for($campaign)->create();
        CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();
    }

    Assessment::factory()
        ->for($candidate)
        ->for($firstCampaign)
        ->create();
    startCandidateExamSession($candidate, $secondCampaign);

    $this->actingAs($candidate)
        ->get(route('candidate.exam'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('state', 'campaign_picker')
            ->where('campaign', null)
            ->has('campaigns', 2)
            ->where('campaigns.0.id', $secondCampaign->id)
            ->where('campaigns.0.title', 'Frontend Engineer Campaign')
            ->where('campaigns.0.role_title', 'Frontend Engineer')
            ->where('campaigns.0.team.name', $secondCampaign->team->name)
            ->where('campaigns.0.progress', 'in_progress')
            ->where('campaigns.1.id', $firstCampaign->id)
            ->where('campaigns.1.title', 'Backend Engineer Campaign')
            ->where('campaigns.1.progress', 'submitted'),
        );
});

test('unassigned candidate cannot open a campaign exam', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertNotFound();
});

test('candidate cannot open or mutate an exam after its team is deactivated', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();
    $campaign->team->update([
        'status' => 'deactivated',
        'deactivated_at' => now(),
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertNotFound();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertNotFound();
});

test('candidate sees submitted state only for the assigned campaign', function () {
    $candidate = User::factory()->create();
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
            ->where('state', 'ready_to_start')
            ->where('campaign.id', $activeCampaign->id)
            ->missing('assessment'),
        );
});

test('candidate can submit an assessment for an active campaign', function () {
    Bus::fake();
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
        'weight' => 100,
    ]);
    $firstQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::ShortText,
            'prompt' => 'Explain why you would choose PostgreSQL for relational constraints.',
            'expected_rubric' => 'Mentions relational integrity and database constraints.',
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

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => [
                $firstQuestion->id => 'PostgreSQL provides relational integrity and database constraints.',
                $secondQuestion->id => 'Dependency injection passes collaborators from the outside.',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session->fresh()]))
        ->assertRedirect();

    $response = $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.finalize', [$campaign, $session->fresh()]), [
            'resume' => resumePdfUpload(),
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
            'question' => 'Explain why you would choose PostgreSQL for relational constraints.',
            'type' => QuestionType::ShortText->value,
            'points' => 10,
            'answer' => 'PostgreSQL provides relational integrity and database constraints.',
        ])
        ->and($assessment->answers_payload[1])
        ->toMatchArray([
            'question_id' => $secondQuestion->id,
            'question' => 'Explain dependency injection.',
            'rubric' => 'Mentions inversion of control and testability.',
            'type' => QuestionType::LongText->value,
            'answer' => 'Dependency injection passes collaborators from the outside.',
        ]);

    Storage::disk('r2-private')->assertExists($assessment->resume_path);
    Bus::assertDispatched(ScreenResumeWithAi::class, fn (ScreenResumeWithAi $job): bool => $job->assessment->is($assessment));
    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);
});

test('candidate must answer every approved campaign question', function () {
    Bus::fake();
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
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

    $session = startCandidateExamSession($candidate, $campaign);

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => [
                $answeredQuestion->id => 'Answered.',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->from(route('candidate.campaigns.exam', $campaign))
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session->fresh()]))
        ->assertSessionHasErrors("answers.{$missingQuestion->id}")
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    expect(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('candidate cannot submit more than one assessment for the same campaign', function () {
    Bus::fake();
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
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
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertSessionHasErrors('session')
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    Bus::assertNothingDispatched();
});

test('candidate can submit a different active campaign when assigned to both', function () {
    Bus::fake();
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
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

    $session = startCandidateExamSession($candidate, $activeCampaign);

    $this->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$activeCampaign, $session]), [
            'answers' => [
                $question->id => 'New campaign answer.',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$activeCampaign, $session->fresh()]))
        ->assertRedirect();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.finalize', [$activeCampaign, $session->fresh()]), [
            'resume' => resumePdfUpload(),
        ])
        ->assertSessionHasNoErrors();

    expect(Assessment::query()->whereBelongsTo($candidate)->count())->toBe(2);
});

test('candidate cannot submit an inactive campaign', function () {
    Bus::fake();
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
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
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertNotFound();

    expect(Assessment::query()->whereBelongsTo($candidate)->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('candidate can view their own assessment', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    $assessment = Assessment::factory()->for($candidate)->for($campaign)->create([
        'answers_payload' => [
            [
                'question_id' => 7,
                'question' => 'Explain why PostgreSQL supports relational constraints.',
                'rubric' => 'Should not be exposed to candidates.',
                'answer' => 'PostgreSQL provides relational integrity.',
            ],
        ],
    ]);
    assignCandidateToCampaignExam($candidate, $campaign);

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/assessments/show')
            ->where('assessment.id', $assessment->id)
            ->where('assessment.campaign.title', $campaign->title)
            ->where('assessment.campaign.team.name', $campaign->team->name)
            ->where('assessment.status', AssessmentStatus::Submitted->value)
            ->where('assessment.answers_payload.0.question_id', 7)
            ->where('assessment.answers_payload.0.question', 'Explain why PostgreSQL supports relational constraints.')
            ->where('assessment.answers_payload.0.answer', 'PostgreSQL provides relational integrity.')
            ->missing('assessment.answers_payload.0.rubric'),
        );
});

test('candidate cannot view another candidates assessment', function () {
    $candidate = User::factory()->create();
    $otherCandidate = User::factory()->create();
    $assessment = Assessment::factory()->for($otherCandidate)->create();

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertNotFound();
});

test('candidate assessment access requires accepted campaign participation', function () {
    $candidate = User::factory()->create();
    $assessment = Assessment::factory()->for($candidate)->create();

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertNotFound();
});
