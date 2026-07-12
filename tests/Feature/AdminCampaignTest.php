<?php

use App\CampaignStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionGradingMode;
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
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $campaign->id)
                ->where('campaigns.0.title', 'Backend Engineer Screening')
                ->where('campaigns.0.questions_count', 1),
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
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $matchingCampaign->id),
            ),
        );
});

test('admin can create a campaign with a default section', function () {
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
        ->and($campaign->sections()->count())->toBe(1);
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
            'scoring_mode' => 'weighted',
            'weight' => 120,
            'sort_order' => 20,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $section = $campaign->sections()->where('title', 'System Design')->sole();

    expect($section)
        ->description->toBe('Design tradeoff questions.')
        ->duration_minutes->toBe(45)
        ->weight->toBe(120)
        ->sort_order->toBe(20);
});

test('campaign sections are ordered by sort order', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    CampaignSection::factory()->for($campaign)->create([
        'title' => 'Later Section',
        'sort_order' => 30,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.sections.store', $campaign), [
            'title' => 'Earlier Section',
            'description' => 'Runs first in the exam.',
            'duration_minutes' => 20,
            'scoring_mode' => 'weighted',
            'weight' => 100,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors();

    $orderedTitles = $campaign->sections()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('title')
        ->all();

    expect($orderedTitles)->toBe(['Earlier Section', 'Later Section']);
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
            'skill_tags_text' => "Debugging\nLaravel",
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $question = $campaign->questions()->sole();

    expect($question)
        ->campaign_section_id->toBe($section->id)
        ->type->toBe(QuestionType::LongText)
        ->grading_mode->toBe(QuestionGradingMode::Ai)
        ->expected_rubric->toContain('logs')
        ->skill_tags->toBe(['Debugging', 'Laravel']);
});

test('admin can add an auto graded multiple choice question to a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.store', $campaign), [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::MultipleChoice->value,
            'prompt' => 'Which queue driver is configured for HirePilot?',
            'options_text' => "sync\ndatabase\nredis\nsqs",
            'correct_answer_text' => 'database',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors();

    $question = $campaign->questions()->sole();

    expect($question)
        ->type->toBe(QuestionType::MultipleChoice)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->options->toBe(['sync', 'database', 'redis', 'sqs'])
        ->correct_answer->toBe(['database']);
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
                'essay_score' => 40,
                'mcq_score' => 5,
            ],
        ])
        ->assertSessionHasErrors('ranking_weights');

    expect($campaign->refresh()->ranking_weights)->not->toMatchArray([
        'resume_score' => 50,
        'essay_score' => 40,
        'mcq_score' => 5,
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
                'essay_score' => 40,
                'mcq_score' => 20,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->refresh())
        ->ranking_weights->toMatchArray([
            'resume_score' => 40,
            'essay_score' => 40,
            'mcq_score' => 20,
        ])
        ->and($campaign->hasConfiguredRankingWeights())->toBeTrue();
});

test('admin can delete a campaign without submitted assessments', function () {
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
        ->toContain('Campaigns with submitted assessments cannot be deleted.')
        ->toContain('CampaignController.destroy.form.delete');
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
