<?php

use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\ExamSessionStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\ExamSession;
use App\Models\User;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\CampaignInvitationService;
use App\Services\CampaignLifecycleService;
use App\Services\ExamSessionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('campaign lifecycle treats pristine campaigns as inactive', function () {
    $campaign = Campaign::factory()->create();

    expect(app(CampaignLifecycleService::class)->hasCandidateActivity($campaign))->toBeFalse();
});

test('campaign lifecycle detects pending invitations sessions and assessments as activity', function (string $kind) {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    match ($kind) {
        'pending invitation' => CampaignInvitation::factory()->for($campaign)->create(),
        'accepted invitation' => CampaignInvitation::factory()
            ->for($campaign)
            ->accepted(User::factory()->create())
            ->create(),
        'exam session' => ExamSession::factory()->for($campaign)->create([
            'status' => ExamSessionStatus::Finalized,
        ]),
        'assessment' => Assessment::factory()->for($campaign)->create(),
    };

    expect(app(CampaignLifecycleService::class)->hasCandidateActivity($campaign))->toBeTrue();
})->with([
    'pending invitation',
    'accepted invitation',
    'exam session',
    'assessment',
]);

test('definition mutation atomically rechecks activity created from a stale campaign view', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->active()->create([
        'title' => 'Original title',
    ]);
    $staleCampaign = $campaign->fresh();

    app(CampaignInvitationService::class)->create(
        $campaign,
        'candidate@example.com',
        $admin,
        sendEmail: false,
    );

    expect(fn () => app(CampaignLifecycleService::class)->withEditableDefinition(
        $staleCampaign,
        fn (Campaign $lockedCampaign) => $lockedCampaign->update(['title' => 'Stale write']),
    ))->toThrow(ValidationException::class, 'definition is frozen')
        ->and($campaign->fresh()->title)->toBe('Original title');
});

test('archived campaign is revalidated when a stale request tries to start an exam', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    $staleCampaign = $campaign->fresh();

    app(CampaignLifecycleService::class)->archive($campaign);

    expect(fn () => app(ExamSessionService::class)->startSession($candidate, $staleCampaign))
        ->toThrow(ValidationException::class, 'not accepting new Exam Sessions')
        ->and($campaign->examSessions()->exists())->toBeFalse();
});

test('archive rechecks sessions created after a stale archive view', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create([
        'status' => QuestionStatus::Approved,
    ]);
    $staleCampaign = $campaign->fresh();

    app(ExamSessionService::class)->startSession($candidate, $campaign);

    expect(fn () => app(CampaignLifecycleService::class)->archive($staleCampaign))
        ->toThrow(ValidationException::class, 'exam is in progress')
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Active);
});

test('frozen campaign definition mutations are rejected', function (string $routeName, string $method, callable $payload) {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Frozen Source',
        'role_title' => 'Backend Engineer',
        'status' => CampaignStatus::Active,
        'ranking_weights' => [
            'resume_score' => 35,
            'assessment_score' => 65,
        ],
    ]);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge',
        'sort_order' => 10,
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'prompt' => 'Explain indexes.',
            'status' => QuestionStatus::Approved,
            'sort_order' => 10,
        ]);

    CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'pending@example.com',
    ]);

    $originalCampaign = $campaign->fresh()->only([
        'title',
        'role_title',
        'status',
        'ranking_weights',
        'threshold_score',
    ]);
    $originalSectionCount = $campaign->sections()->count();
    $originalQuestionCount = $campaign->questions()->count();
    $originalQuestionPrompt = $question->prompt;

    $url = match ($routeName) {
        'admin.campaigns.edit' => route($routeName, $campaign),
        'admin.campaigns.update',
        'admin.campaigns.publish',
        'admin.campaigns.draft',
        'admin.campaigns.ranking.update',
        'admin.campaigns.generate-assessment',
        'admin.campaigns.sections.store',
        'admin.campaigns.questions.store',
        'admin.campaigns.questions.approve-all',
        'admin.campaigns.questions.discard-all' => route($routeName, $campaign),
        'admin.campaigns.sections.update',
        'admin.campaigns.sections.destroy' => route($routeName, [$campaign, $section]),
        default => route($routeName, [$campaign, $question]),
    };

    $response = $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->{$method}($url, $payload($campaign, $section, $question));

    if ($method === 'get') {
        $response->assertRedirect(route('admin.campaigns.show', $campaign));
    } else {
        $response->assertRedirect(route('admin.campaigns.show', $campaign));
    }

    $response->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->only([
        'title',
        'role_title',
        'status',
        'ranking_weights',
        'threshold_score',
    ]))->toBe($originalCampaign)
        ->and($campaign->sections()->count())->toBe($originalSectionCount)
        ->and($campaign->questions()->count())->toBe($originalQuestionCount)
        ->and($question->fresh()->prompt)->toBe($originalQuestionPrompt);
})->with([
    'edit' => [
        'admin.campaigns.edit',
        'get',
        fn () => [],
    ],
    'update' => [
        'admin.campaigns.update',
        'patch',
        fn () => [
            'title' => 'Mutated Title',
            'role_title' => 'Mutated Role',
            'seniority' => 'Senior',
            'job_description' => 'Changed',
            'required_skills' => "Changed\nSkills",
            'language' => 'English',
            'threshold_score' => 90,
        ],
    ],
    'publish' => [
        'admin.campaigns.publish',
        'post',
        fn () => [],
    ],
    'draft' => [
        'admin.campaigns.draft',
        'post',
        fn () => [],
    ],
    'ranking' => [
        'admin.campaigns.ranking.update',
        'patch',
        fn () => [
            'ranking_weights' => [
                'resume_score' => 10,
                'assessment_score' => 90,
            ],
        ],
    ],
    'generate assessment' => [
        'admin.campaigns.generate-assessment',
        'post',
        fn () => [
            'question_count' => 3,
            'difficulty' => 'medium',
        ],
    ],
    'section store' => [
        'admin.campaigns.sections.store',
        'post',
        fn () => [
            'title' => 'Injected Section',
            'description' => 'Should not persist.',
            'duration_minutes' => 20,
            'weight' => 100,
            'sort_order' => 99,
        ],
    ],
    'section update' => [
        'admin.campaigns.sections.update',
        'patch',
        fn () => [
            'title' => 'Mutated Section',
            'description' => 'Should not persist.',
            'duration_minutes' => 20,
            'weight' => 100,
            'sort_order' => 99,
        ],
    ],
    'section destroy' => [
        'admin.campaigns.sections.destroy',
        'delete',
        fn () => [],
    ],
    'question store' => [
        'admin.campaigns.questions.store',
        'post',
        fn (Campaign $campaign, CampaignSection $section) => [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::LongText->value,
            'prompt' => 'Injected question.',
            'expected_rubric' => 'Should not persist.',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 99,
        ],
    ],
    'question update' => [
        'admin.campaigns.questions.update',
        'patch',
        fn (Campaign $campaign, CampaignSection $section, CampaignQuestion $question) => [
            'campaign_section_id' => $section->id,
            'type' => $question->type->value,
            'prompt' => 'Mutated prompt.',
            'expected_rubric' => 'Mutated rubric.',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ],
    ],
    'question destroy' => [
        'admin.campaigns.questions.destroy',
        'delete',
        fn () => [],
    ],
    'question approve' => [
        'admin.campaigns.questions.approve',
        'post',
        fn () => [],
    ],
    'question approve all' => [
        'admin.campaigns.questions.approve-all',
        'post',
        fn () => [],
    ],
    'question discard all' => [
        'admin.campaigns.questions.discard-all',
        'delete',
        fn () => [],
    ],
]);

test('definition guard middleware is applied only to definition mutation routes', function () {
    $guarded = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('campaign-definition-editable', $route->gatherMiddleware(), true))
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->sort()
        ->values()
        ->all();

    expect($guarded)->toBe([
        'admin.campaigns.draft',
        'admin.campaigns.edit',
        'admin.campaigns.generate-assessment',
        'admin.campaigns.publish',
        'admin.campaigns.questions.approve',
        'admin.campaigns.questions.approve-all',
        'admin.campaigns.questions.destroy',
        'admin.campaigns.questions.discard-all',
        'admin.campaigns.questions.reorder',
        'admin.campaigns.questions.store',
        'admin.campaigns.questions.update',
        'admin.campaigns.ranking.update',
        'admin.campaigns.sections.destroy',
        'admin.campaigns.sections.generate-question',
        'admin.campaigns.sections.reorder',
        'admin.campaigns.sections.store',
        'admin.campaigns.sections.update',
        'admin.campaigns.update',
    ]);
});

test('used campaign cannot be archived while an exam session is in progress', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
    ]);
    CampaignInvitation::factory()->for($campaign)->create();
    ExamSession::factory()->for($campaign)->create([
        'status' => ExamSessionStatus::InProgress,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.archive', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Active);
});

test('used campaign can be archived after exam sessions complete', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
        'activated_at' => now()->subDay(),
    ]);
    CampaignInvitation::factory()->for($campaign)->create();
    ExamSession::factory()->for($campaign)->create([
        'status' => ExamSessionStatus::Finalized,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.archive', $campaign))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->status->toBe(CampaignStatus::Archived)
        ->activated_at->toBeNull();
});

test('archiving a campaign revokes its pending invitations', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
    ]);
    $pendingInvitation = CampaignInvitation::factory()->for($campaign)->create();
    $acceptedInvitation = CampaignInvitation::factory()
        ->for($campaign)
        ->accepted(User::factory()->create())
        ->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.archive', $campaign))
        ->assertSessionHasNoErrors();

    expect($pendingInvitation->fresh()->status)->toBe(CampaignInvitationStatus::Revoked)
        ->and($acceptedInvitation->fresh()->status)->toBe(CampaignInvitationStatus::Accepted);
});

test('used campaign cannot move back to draft', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Archived,
    ]);
    CampaignInvitation::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.draft', $campaign))
        ->assertSessionHasErrors('campaign');

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Archived);
});

test('pristine campaign can still move from active back to draft', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
        'activated_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.draft', $campaign))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->status->toBe(CampaignStatus::Draft)
        ->activated_at->toBeNull();
});

test('admin can clone a used campaign into an independent same-team draft', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Backend Screening',
        'role_title' => 'Backend Engineer',
        'seniority' => 'Mid-level',
        'job_description' => 'Build APIs.',
        'required_skills' => ['Laravel', 'PostgreSQL'],
        'language' => 'English',
        'threshold_score' => 80,
        'ranking_weights' => [
            'resume_score' => 40,
            'assessment_score' => 60,
        ],
        'status' => CampaignStatus::Active,
        'ai_generation_audit' => [
            ['model' => 'qwen', 'generated_at' => now()->toIso8601String()],
        ],
        'activated_at' => now()->subDay(),
    ]);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Core',
        'description' => 'Core skills',
        'duration_minutes' => 30,
        'weight' => 100,
        'sort_order' => 10,
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::LongText,
            'prompt' => 'Explain how you would choose a queue driver.',
            'expected_rubric' => 'Compares reliability, throughput, and operational tradeoffs.',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => true,
            'status' => QuestionStatus::Approved,
            'is_required' => true,
            'sort_order' => 5,
        ]);

    CampaignInvitation::factory()->for($campaign)->create();
    ExamSession::factory()->for($campaign)->create([
        'status' => ExamSessionStatus::Finalized,
    ]);
    Assessment::factory()->for($campaign)->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.clone', $campaign))
        ->assertSessionHasNoErrors();

    $clone = Campaign::query()->where('title', 'Backend Screening (Copy)')->sole();

    $response->assertRedirect(route('admin.campaigns.show', $clone));

    expect($clone)
        ->id->not->toBe($campaign->id)
        ->team_id->toBe($campaign->team_id)
        ->created_by->toBe($admin->id)
        ->role_title->toBe('Backend Engineer')
        ->seniority->toBe('Mid-level')
        ->job_description->toBe('Build APIs.')
        ->required_skills->toBe(['Laravel', 'PostgreSQL'])
        ->language->toBe('English')
        ->threshold_score->toBe(80)
        ->ranking_weights->toMatchArray([
            'resume_score' => 40,
            'assessment_score' => 60,
        ])
        ->status->toBe(CampaignStatus::Draft)
        ->ai_generation_audit->toBeNull()
        ->activated_at->toBeNull()
        ->and($clone->invitations()->exists())->toBeFalse()
        ->and($clone->examSessions()->exists())->toBeFalse()
        ->and($clone->assessments()->exists())->toBeFalse()
        ->and($campaign->fresh())
        ->title->toBe('Backend Screening')
        ->status->toBe(CampaignStatus::Active)
        ->and($campaign->invitations()->count())->toBe(1)
        ->and($campaign->examSessions()->count())->toBe(1)
        ->and($campaign->assessments()->count())->toBe(1);

    $clonedSection = $clone->sections()->sole();
    $clonedQuestion = $clone->questions()->sole();

    expect($clonedSection)
        ->id->not->toBe($section->id)
        ->title->toBe('Core')
        ->description->toBe('Core skills')
        ->duration_minutes->toBe(30)
        ->weight->toBe(100)
        ->sort_order->toBe(10)
        ->and($clonedQuestion)
        ->id->not->toBe($question->id)
        ->campaign_section_id->toBe($clonedSection->id)
        ->type->toBe(QuestionType::LongText)
        ->prompt->toBe('Explain how you would choose a queue driver.')
        ->expected_rubric->toContain('operational tradeoffs')
        ->points->toBe(10)
        ->difficulty->toBe('easy')
        ->ai_generated->toBeTrue()
        ->status->toBe(QuestionStatus::Approved)
        ->is_required->toBeTrue()
        ->sort_order->toBe(5);
});

test('campaign clone is denied for a cross-team actor', function () {
    $owner = User::factory()->teamOwner()->create();
    $otherOwner = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($owner, 'creator')->create([
        'title' => 'Owned Campaign',
    ]);

    $this->actingAs($otherOwner)
        ->post(route('admin.campaigns.clone', $campaign))
        ->assertNotFound();

    expect(Campaign::query()->where('title', 'Owned Campaign (Copy)')->exists())->toBeFalse();
});

test('campaign show exposes frozen definition flags after invitation', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
    ]);
    CampaignInvitation::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->get(route('admin.campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/show')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('campaign.definition_frozen', true)
                ->where('campaign.can_clone', true)
                ->where('campaign.can_publish', false),
            ),
        );
});
