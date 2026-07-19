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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view campaigns', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Backend Engineer Screening',
        'role_title' => 'Backend Engineer',
        'status' => CampaignStatus::Active,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();

    $this->actingAs($admin)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $campaign->id)
                ->where('campaigns.data.0.title', 'Backend Engineer Screening')
                ->where('campaigns.data.0.questions_count', 1)
                ->where('campaigns.data.0.job_description', $campaign->job_description)
                ->where('campaigns.data.0.required_skills', $campaign->required_skills)
                ->where('campaigns.per_page', 15)
                ->where('campaigns.total', 1),
            ),
        );
});

test('admin can search and filter campaigns', function () {
    $admin = User::factory()->teamOwner()->create();
    $matchingCampaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Frontend Engineer Screening',
        'role_title' => 'Frontend Engineer',
        'status' => CampaignStatus::Active,
    ]);

    Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Backend Engineer Screening',
        'role_title' => 'Backend Engineer',
        'status' => CampaignStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.index', [
            'search' => 'frontend',
            'status' => CampaignStatus::Active->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/index')
            ->where('filters.search', 'frontend')
            ->where('filters.status', CampaignStatus::Active->value)
            ->has('statusOptions', 4)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $matchingCampaign->id)
                ->where('campaigns.total', 1),
            ),
        );
});

test('admin campaign search treats SQL wildcard characters literally', function () {
    $admin = User::factory()->teamOwner()->create();
    $matchingCampaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => '100% Remote Hiring',
    ]);
    Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Office Hiring',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', '%')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $matchingCampaign->id),
            ),
        );
});

test('admin campaign index paginates results', function () {
    $admin = User::factory()->teamOwner()->create();
    Campaign::factory()
        ->for($admin, 'creator')
        ->for($admin->currentTeam)
        ->count(16)
        ->create();

    $this->actingAs($admin)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('campaigns.data', 15)
                ->where('campaigns.per_page', 15)
                ->where('campaigns.total', 16)
                ->where('campaigns.last_page', 2),
            ),
        );
});

test('admin campaign pagination uses campaign ids to break created at ties', function () {
    $admin = User::factory()->teamOwner()->create();
    $createdAt = now()->startOfSecond();
    $campaigns = Campaign::factory()
        ->for($admin, 'creator')
        ->for($admin->currentTeam)
        ->count(16)
        ->create([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $campaigns->min('id')),
            ),
        );
});

test('admin can create a campaign without a default section', function () {
    $admin = User::factory()->teamOwner()->create();
    $currentTeam = $admin->currentTeam;

    $response = $this->actingAs($admin)
        ->from(route('admin.campaigns.create'))
        ->post(route('admin.campaigns.store'), [
            'title' => 'Laravel Backend Hiring',
            'role_title' => 'Backend Engineer',
            'seniority' => 'Mid-level',
            'job_description' => 'Build APIs and queue workers.',
            'required_skills' => "Laravel\nPostgreSQL\nQueues",
            'language' => 'English',
            'threshold_score' => 80,
        ])
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->where('title', 'Laravel Backend Hiring')->sole();

    $response->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign)
        ->team_id->toBe($currentTeam->id)
        ->created_by->toBe($admin->id)
        ->role_title->toBe('Backend Engineer')
        ->required_skills->toBe(['Laravel', 'PostgreSQL', 'Queues'])
        ->status->toBe(CampaignStatus::Draft)
        ->activated_at->toBeNull()
        ->ranking_weights->toMatchArray(Campaign::defaultRankingWeights())
        ->and($campaign->sections()->exists())->toBeFalse();
});

test('admin cannot create a campaign for a deactivated current team', function () {
    $admin = User::factory()->teamOwner()->create();
    $admin->currentTeam->update([
        'status' => 'deactivated',
        'deactivated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.create'))
        ->post(route('admin.campaigns.store'), [
            'title' => 'Blocked Campaign',
            'role_title' => 'Backend Engineer',
            'threshold_score' => 75,
        ])
        ->assertForbidden();

    expect(Campaign::query()->where('title', 'Blocked Campaign')->exists())->toBeFalse();
});

test('legacy admin without a current team cannot create a campaign', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.create'))
        ->post(route('admin.campaigns.store'), [
            'title' => 'Unowned Campaign',
            'role_title' => 'Backend Engineer',
            'threshold_score' => 75,
        ])
        ->assertForbidden();

    expect(Campaign::query()->where('title', 'Unowned Campaign')->exists())->toBeFalse();
});

test('admin can update a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Draft,
        'activated_at' => null,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.edit', $campaign))
        ->patch(route('admin.campaigns.update', $campaign), [
            'title' => 'Updated Campaign',
            'role_title' => 'Senior Backend Engineer',
            'seniority' => 'Senior',
            'job_description' => 'Own architecture.',
            'required_skills' => "Architecture\nLeadership",
            'language' => 'English',
            'threshold_score' => 85,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->title->toBe('Updated Campaign')
        ->role_title->toBe('Senior Backend Engineer')
        ->required_skills->toBe(['Architecture', 'Leadership'])
        ->threshold_score->toBe(85)
        ->status->toBe(CampaignStatus::Draft)
        ->activated_at->toBeNull();
});

test('admin can move a campaign from active back to draft', function () {
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

test('admin can archive a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::Active,
        'activated_at' => now()->subDay(),
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

test('admin can approve a generated draft campaign question', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::QuestionReview,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'ai_generated' => true,
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.approve', [$campaign, $question]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($question->refresh()->status)->toBe(QuestionStatus::Approved);
});

test('admin can approve all generated draft campaign questions', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::QuestionReview,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->count(2)
        ->create([
            'ai_generated' => true,
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.approve-all', $campaign))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->questions()->where('status', QuestionStatus::Draft->value)->exists())->toBeFalse()
        ->and($campaign->questions()->where('status', QuestionStatus::Approved->value)->count())->toBe(2);
});

test('admin can discard all draft campaign questions without deleting approved questions', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::QuestionReview,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    $draft = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Draft]);
    $approved = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $this->actingAs($admin)
        ->delete(route('admin.campaigns.questions.discard-all', $campaign))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $this->assertModelMissing($draft);
    $this->assertModelExists($approved);
});

test('campaign cannot be published while generated questions are still drafts', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::QuestionReview,
        'activated_at' => null,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'ai_generated' => true,
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.publish', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->status->toBe(CampaignStatus::QuestionReview)
        ->activated_at->toBeNull();
});

test('admin can publish a campaign after questions are approved', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'status' => CampaignStatus::QuestionReview,
        'activated_at' => null,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.publish', $campaign))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->status->toBe(CampaignStatus::Active)
        ->activated_at->not->toBeNull();
});

test('admin can add a campaign section', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.sections.store', $campaign), [
            'title' => 'System Design',
            'description' => 'Design tradeoff questions.',
            'duration_minutes' => 45,
            'weight' => 20,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $section = $campaign->sections()->where('title', 'System Design')->sole();

    expect($section)
        ->description->toBe('Design tradeoff questions.')
        ->duration_minutes->toBe(45)
        ->weight->toBe(100)
        ->sort_order->toBe(10);
});

test('admin can update a campaign section', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.campaigns.sections.update', [$campaign, $section]), [
            'title' => 'Technical Reasoning',
            'description' => 'Evaluate practical engineering decisions.',
            'duration_minutes' => 35,
            'weight' => 50,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($section->refresh())
        ->title->toBe('Technical Reasoning')
        ->description->toBe('Evaluate practical engineering decisions.')
        ->duration_minutes->toBe(35)
        ->weight->toBe(100);
});

test('admin can reorder campaign sections', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $laterSection = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Later Section',
        'sort_order' => 30,
    ]);
    $earlierSection = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Earlier Section',
        'sort_order' => 10,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.campaigns.sections.reorder', $campaign), [
            'section_ids' => [$laterSection->id, $earlierSection->id],
        ])
        ->assertSessionHasNoErrors();

    $orderedTitles = $campaign->sections()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('title')
        ->all();

    expect($orderedTitles)->toBe(['Later Section', 'Earlier Section']);
});

test('section score contributions stay normalized to one hundred percent', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $firstSection = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Technical Fundamentals',
        'weight' => 100,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.sections.store', $campaign), [
            'title' => 'System Design',
            'description' => null,
            'duration_minutes' => 30,
            'weight' => 30,
        ])
        ->assertSessionHasNoErrors();

    $secondSection = $campaign->sections()->where('title', 'System Design')->sole();

    expect($firstSection->refresh()->weight)->toBe(70)
        ->and($secondSection->weight)->toBe(30)
        ->and($campaign->sections()->sum('weight'))->toBe(100);

    $this->actingAs($admin)
        ->patch(route('admin.campaigns.sections.update', [$campaign, $secondSection]), [
            'title' => $secondSection->title,
            'description' => $secondSection->description,
            'duration_minutes' => $secondSection->duration_minutes,
            'weight' => 40,
        ])
        ->assertSessionHasNoErrors();

    expect($firstSection->refresh()->weight)->toBe(60)
        ->and($secondSection->refresh()->weight)->toBe(40)
        ->and($campaign->sections()->sum('weight'))->toBe(100);

    $this->actingAs($admin)
        ->delete(route('admin.campaigns.sections.destroy', [$campaign, $secondSection]))
        ->assertSessionHasNoErrors();

    expect($firstSection->refresh()->weight)->toBe(100);
});

test('admin can reorder questions within a section', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $firstQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['sort_order' => 10]);
    $secondQuestion = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['sort_order' => 20]);

    $this->actingAs($admin)
        ->patch(route('admin.campaigns.questions.reorder', [$campaign, $section]), [
            'question_ids' => [$secondQuestion->id, $firstQuestion->id],
        ])
        ->assertSessionHasNoErrors();

    expect($section->questions()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$secondQuestion->id, $firstQuestion->id]);
});

test('admin can add an ai graded text question to a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.store', $campaign), [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::LongText->value,
            'prompt' => 'Explain how you would debug a slow API.',
            'expected_rubric' => 'Mentions logs, metrics, queries, N+1, and verification.',
            'points' => 20,
            'difficulty' => 'medium',
            'ai_generated' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $question = $campaign->questions()->sole();

    expect($question)
        ->campaign_section_id->toBe($section->id)
        ->type->toBe(QuestionType::LongText)
        ->expected_rubric->toContain('logs')
        ->points->toBe(20)
        ->is_required->toBeTrue()
        ->sort_order->toBe(10);
});

test('campaign rejects removed objective question types', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.store', $campaign), [
            'campaign_section_id' => $section->id,
            'type' => 'multiple_choice',
            'prompt' => 'Pick a queue driver.',
            'expected_rubric' => 'Not applicable.',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasErrors('type');

    expect($campaign->questions()->exists())->toBeFalse();
});

test('campaign question section must belong to the campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $otherCampaign = Campaign::factory()->for($admin, 'creator')->create();
    $otherSection = CampaignSection::factory()->for($otherCampaign)->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.store', $campaign), [
            'campaign_section_id' => $otherSection->id,
            'type' => QuestionType::LongText->value,
            'prompt' => 'Explain database indexing.',
            'expected_rubric' => 'Mentions read performance and write tradeoffs.',
            'points' => 10,
            'difficulty' => 'medium',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasErrors('campaign_section_id')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->questions()->exists())->toBeFalse();
});

test('candidate cannot access campaign management', function (string $route, string $method) {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $url = match ($route) {
        'admin.campaigns.index', 'admin.campaigns.create', 'admin.campaigns.store' => route($route),
        default => route($route, $campaign),
    };

    $this->actingAs($candidate)
        ->call($method, $url)
        ->assertForbidden();
})->with([
    ['admin.campaigns.index', 'GET'],
    ['admin.campaigns.create', 'GET'],
    ['admin.campaigns.store', 'POST'],
    ['admin.campaigns.show', 'GET'],
    ['admin.campaigns.edit', 'GET'],
    ['admin.campaigns.update', 'PATCH'],
    ['admin.campaigns.publish', 'POST'],
    ['admin.campaigns.archive', 'POST'],
    ['admin.campaigns.draft', 'POST'],
    ['admin.campaigns.ranking.update', 'PATCH'],
    ['admin.campaigns.questions.approve-all', 'POST'],
    ['admin.campaigns.questions.discard-all', 'DELETE'],
    ['admin.campaigns.destroy', 'DELETE'],
]);

test('candidate cannot approve campaign questions', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.questions.approve', [$campaign, $question]))
        ->assertForbidden();
});

test('candidate cannot update campaign questions', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create();

    $this->actingAs($candidate)
        ->patch(route('admin.campaigns.questions.update', [$campaign, $question]), [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::LongText->value,
            'prompt' => 'Candidate should not update this.',
            'expected_rubric' => 'Blocked by role middleware.',
            'points' => 10,
            'difficulty' => 'medium',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertForbidden();
});

test('campaign rejects ranking weights that do not total 100', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->patch(route('admin.campaigns.ranking.update', $campaign), [
            'ranking_weights' => [
                'resume_score' => 50,
                'assessment_score' => 40,
            ],
        ])
        ->assertSessionHasErrors('ranking_weights');

    expect($campaign->refresh()->ranking_weights)->not->toMatchArray([
        'resume_score' => 50,
        'assessment_score' => 40,
    ]);
});

test('campaign stores language and required skills from the main form', function () {
    $admin = User::factory()->teamOwner()->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.create'))
        ->post(route('admin.campaigns.store'), [
            'title' => 'Weighted Campaign',
            'role_title' => 'Backend Engineer',
            'threshold_score' => 75,
            'language' => 'Indonesian',
            'required_skills' => "Laravel\nQueues",
        ])
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->where('title', 'Weighted Campaign')->sole();

    expect($campaign)
        ->language->toBe('Indonesian')
        ->required_skills->toBe(['Laravel', 'Queues'])
        ->ranking_weights->toMatchArray(Campaign::defaultRankingWeights());
});

test('admin can update campaign ranking weights from the detail page', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->patch(route('admin.campaigns.ranking.update', $campaign), [
            'ranking_weights' => [
                'resume_score' => 40,
                'assessment_score' => 60,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->ranking_weights->toMatchArray([
            'resume_score' => 40,
            'assessment_score' => 60,
        ])
        ->and($campaign->hasConfiguredRankingWeights())->toBeTrue();
});

test('admin can delete a pristine campaign without invitations sessions or assessments', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertRedirect(route('admin.campaigns.index'));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeFalse();
});

test('campaign index requires confirmation before deleting a campaign', function () {
    $source = file_get_contents(resource_path('js/pages/admin/campaigns/index.tsx'));

    expect($source)
        ->toContain('DialogTitle')
        ->toContain('Delete campaign?')
        ->toContain('Campaigns with invitations, exam attempts, or')
        ->toContain('assessments cannot be deleted.')
        ->toContain('CampaignController.destroy.form.delete');
});

test('admin cannot delete a campaign that has a pending invitation', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $invitation = CampaignInvitation::factory()->for($campaign)->create([
        'invited_by' => $admin->id,
        'status' => CampaignInvitationStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeTrue()
        ->and(CampaignInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});

test('admin cannot delete a campaign that has an accepted invitation', function () {
    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $invitation = CampaignInvitation::factory()->for($campaign)->accepted($candidate)->create([
        'invited_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeTrue()
        ->and(CampaignInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});

test('admin cannot delete a campaign that has an in-progress exam session', function () {
    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $session = ExamSession::factory()->for($campaign)->for($candidate)->create([
        'status' => ExamSessionStatus::InProgress,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeTrue()
        ->and(ExamSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

test('admin cannot delete a campaign that has a finalized exam session without an assessment', function () {
    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $session = ExamSession::factory()->for($campaign)->for($candidate)->create([
        'status' => ExamSessionStatus::Finalized,
        'assessment_id' => null,
        'finalized_at' => now(),
        'submission_reason' => 'candidate_submitted',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeTrue()
        ->and(ExamSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

test('admin cannot delete a campaign that already has assessments', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    Assessment::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->delete(route('admin.campaigns.destroy', $campaign))
        ->assertSessionHasErrors('campaign')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeTrue();
});
