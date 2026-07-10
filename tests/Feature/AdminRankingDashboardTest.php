<?php

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view candidate ranking leaderboard ordered by ranking score', function () {
    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->create([
        'title' => 'Backend Hiring',
        'role_title' => 'Backend Engineer',
    ]);
    $topCandidate = User::factory()->candidate()->create([
        'name' => 'Top Candidate',
        'email' => 'top@example.com',
    ]);
    $secondCandidate = User::factory()->candidate()->create([
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
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->missing('summary')
            ->missing('charts')
            ->where('filters.search', '')
            ->where('filters.status', 'all')
            ->where('filters.date_range', 'all')
            ->has('statusOptions')
            ->has('dateRangeOptions', 4)
            ->where('rankings.0.assessment_id', $topAssessment->id)
            ->where('rankings.0.rank', 1)
            ->where('rankings.0.candidate_name', 'Top Candidate')
            ->where('rankings.0.campaign_title', 'Backend Hiring')
            ->where('rankings.0.role_title', 'Backend Engineer')
            ->where('rankings.0.ranking_score', 91)
            ->missing('rankings.0.matched_skills')
            ->missing('rankings.0.section_scores')
            ->where('rankings.1.assessment_id', $secondAssessment->id),
        );
});

test('admin can search and filter candidate rankings', function () {
    $admin = User::factory()->admin()->create();
    $backendCampaign = Campaign::factory()->create([
        'title' => 'Backend Hiring',
        'role_title' => 'Backend Engineer',
    ]);
    $frontendCampaign = Campaign::factory()->create([
        'title' => 'Frontend Hiring',
        'role_title' => 'Frontend Engineer',
    ]);
    $matchingCandidate = User::factory()->candidate()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $otherCandidate = User::factory()->candidate()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $matchingAssessment = Assessment::factory()
        ->for($matchingCandidate)
        ->for($backendCampaign)
        ->create([
            'ranking_score' => 91,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now()->subDays(2),
        ]);

    Assessment::factory()
        ->for($otherCandidate)
        ->for($frontendCampaign)
        ->create([
            'ranking_score' => 88,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(20),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'search' => 'ada',
            'status' => AssessmentStatus::PendingApproval->value,
            'date_range' => '7d',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings', 1)
            ->where('rankings.0.assessment_id', $matchingAssessment->id)
            ->where('filters.search', 'ada')
            ->where('filters.status', AssessmentStatus::PendingApproval->value)
            ->where('filters.date_range', '7d')
            ->has('statusOptions', count(AssessmentStatus::cases()))
            ->has('dateRangeOptions', 4),
        );
});

test('admin can filter candidate rankings by date range', function () {
    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->create();
    $recentCandidate = User::factory()->candidate()->create([
        'name' => 'Recent Candidate',
    ]);
    $olderCandidate = User::factory()->candidate()->create([
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
            'ranking_score' => 85,
            'status' => AssessmentStatus::Evaluated,
            'evaluated_at' => now()->subDays(40),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index', [
            'date_range' => '7d',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->has('rankings', 1)
            ->where('rankings.0.assessment_id', $recentAssessment->id)
            ->where('filters.date_range', '7d'),
        );
});

test('candidate cannot access candidate ranking leaderboard', function () {
    $candidate = User::factory()->candidate()->create();

    $this->actingAs($candidate)
        ->get(route('admin.rankings.index'))
        ->assertForbidden();
});
