<?php

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view candidate ranking leaderboard ordered by ranking score within a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Backend Hiring',
        'role_title' => 'Backend Engineer',
    ]);
    $topCandidate = User::factory()->create([
        'name' => 'Top Candidate',
        'email' => 'top@example.com',
    ]);
    $secondCandidate = User::factory()->create([
        'name' => 'Second Candidate',
        'email' => 'second@example.com',
    ]);

    $secondAssessment = Assessment::factory()
        ->for($secondCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 72,
            'resume_score' => 70,
            'essay_score' => 74,
            'mcq_score' => 70,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(1),
        ]);
    $topAssessment = Assessment::factory()
        ->for($topCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 91,
            'resume_score' => 88,
            'essay_score' => 92,
            'mcq_score' => 96,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now()->subHours(2),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->missing('summary')
            ->missing('charts')
            ->where('filters.campaign', (string) $campaign->id)
            ->where('filters.search', '')
            ->where('filters.status', 'all')
            ->where('filters.date_range', 'all')
            ->has('campaignOptions', 1)
            ->where('campaignOptions.0.value', (string) $campaign->id)
            ->has('statusOptions')
            ->has('dateRangeOptions', 4)
            ->where('rankings.per_page', 25)
            ->where('rankings.data.0.assessment_id', $topAssessment->id)
            ->where('rankings.data.0.rank', 1)
            ->where('rankings.data.0.candidate_name', 'Top Candidate')
            ->where('rankings.data.0.campaign_title', 'Backend Hiring')
            ->where('rankings.data.0.role_title', 'Backend Engineer')
            ->where('rankings.data.0.ranking_score', 91)
            ->where('rankings.data.0.evaluated_at', $topAssessment->fresh()->evaluated_at?->toIso8601String())
            ->missing('rankings.data.0.matched_skills')
            ->missing('rankings.data.0.section_scores')
            ->where('rankings.data.1.assessment_id', $secondAssessment->id)
            ->where('rankings.data.1.rank', 2),
        );
});

test('admin rankings are scoped per campaign and default to the first available campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $backendCampaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Backend Hiring',
    ]);
    $frontendCampaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Frontend Hiring',
    ]);

    $backendTop = Assessment::factory()
        ->for(User::factory()->create(['name' => 'Backend Top']))
        ->for($backendCampaign)
        ->create([
            'ranking_score' => 80,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now(),
        ]);
    Assessment::factory()
        ->for(User::factory()->create(['name' => 'Backend Second']))
        ->for($backendCampaign)
        ->create([
            'ranking_score' => 70,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now(),
        ]);

    $frontendTop = Assessment::factory()
        ->for(User::factory()->create(['name' => 'Frontend Top']))
        ->for($frontendCampaign)
        ->create([
            'ranking_score' => 95,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now(),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->where('filters.campaign', (string) $backendCampaign->id)
            ->has('rankings.data', 2)
            ->where('rankings.data.0.assessment_id', $backendTop->id)
            ->where('rankings.data.0.rank', 1)
            ->where('rankings.data.0.candidate_name', 'Backend Top'),
        );

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $frontendCampaign->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->where('filters.campaign', (string) $frontendCampaign->id)
            ->has('rankings.data', 1)
            ->where('rankings.data.0.assessment_id', $frontendTop->id)
            ->where('rankings.data.0.rank', 1)
            ->where('rankings.data.0.candidate_name', 'Frontend Top'),
        );
});

test('admin ranking numbers stay stable when search status or date filters are applied', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Stable Rank Campaign',
    ]);

    $first = Assessment::factory()
        ->for(User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]))
        ->for($campaign)
        ->create([
            'ranking_score' => 95,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now()->subDays(2),
        ]);
    Assessment::factory()
        ->for(User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]))
        ->for($campaign)
        ->create([
            'ranking_score' => 88,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(2),
        ]);
    $third = Assessment::factory()
        ->for(User::factory()->create([
            'name' => 'Alan Turing',
            'email' => 'alan@example.com',
        ]))
        ->for($campaign)
        ->create([
            'ranking_score' => 81,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now()->subDays(2),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $campaign->id,
            'search' => 'alan',
            'status' => AssessmentStatus::PendingApproval->value,
            'date_range' => '7d',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings.data', 1)
            ->where('rankings.data.0.assessment_id', $third->id)
            ->where('rankings.data.0.rank', 3)
            ->where('filters.search', 'alan')
            ->where('filters.status', AssessmentStatus::PendingApproval->value)
            ->where('filters.date_range', '7d'),
        );

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $campaign->id,
            'status' => AssessmentStatus::PendingApproval->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings.data', 2)
            ->where('rankings.data.0.assessment_id', $first->id)
            ->where('rankings.data.0.rank', 1)
            ->where('rankings.data.1.assessment_id', $third->id)
            ->where('rankings.data.1.rank', 3),
        );
});

test('admin can search and filter candidate rankings within a campaign', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Backend Hiring',
        'role_title' => 'Backend Engineer',
    ]);
    $matchingCandidate = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $otherCandidate = User::factory()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $matchingAssessment = Assessment::factory()
        ->for($matchingCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 91,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now()->subDays(2),
        ]);

    Assessment::factory()
        ->for($otherCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 88,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(20),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $campaign->id,
            'search' => 'ada',
            'status' => AssessmentStatus::PendingApproval->value,
            'date_range' => '7d',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings.data', 1)
            ->where('rankings.data.0.assessment_id', $matchingAssessment->id)
            ->where('rankings.data.0.rank', 1)
            ->where('filters.campaign', (string) $campaign->id)
            ->where('filters.search', 'ada')
            ->where('filters.status', AssessmentStatus::PendingApproval->value)
            ->where('filters.date_range', '7d')
            ->has('statusOptions', count(AssessmentStatus::cases()))
            ->has('dateRangeOptions', 4),
        );
});

test('admin can filter candidate rankings by date range', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
    ]);
    $recentCandidate = User::factory()->create([
        'name' => 'Recent Candidate',
    ]);
    $olderCandidate = User::factory()->create([
        'name' => 'Older Candidate',
    ]);

    $recentAssessment = Assessment::factory()
        ->for($recentCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 90,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(3),
        ]);

    Assessment::factory()
        ->for($olderCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 95,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(40),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $campaign->id,
            'date_range' => '7d',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings.data', 1)
            ->where('rankings.data.0.assessment_id', $recentAssessment->id)
            ->where('rankings.data.0.rank', 2)
            ->where('filters.date_range', '7d'),
        );
});

test('admin rankings paginate beyond the page size', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
    ]);

    foreach (range(1, 26) as $score) {
        Assessment::factory()
            ->for(User::factory()->create())
            ->for($campaign)
            ->create([
                'ranking_score' => $score,
                'status' => AssessmentStatus::Evaluated,
                'evaluated_at' => now()->subMinutes($score),
            ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'campaign' => $campaign->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings.data', 25)
            ->where('rankings.per_page', 25)
            ->where('rankings.total', 26)
            ->where('rankings.last_page', 2)
            ->where('rankings.data.0.rank', 1)
            ->where('rankings.data.0.ranking_score', 26),
        );
});

test('candidate cannot access candidate ranking leaderboard', function () {
    $candidate = User::factory()->create();

    $this->actingAs($candidate)
        ->get(route('admin.rankings.index'))
        ->assertForbidden();
});
