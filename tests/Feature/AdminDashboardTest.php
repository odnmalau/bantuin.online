<?php

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin dashboard includes last-7-day summary, two charts, and needs attention', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Dashboard Campaign',
    ]);
    $olderCandidate = User::factory()->create();
    $newerCandidate = User::factory()->create();

    Assessment::factory()
        ->for($olderCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 80,
            'status' => AssessmentStatus::Evaluated,
            'needs_manual_review' => false,
            'evaluated_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
        ]);

    Assessment::factory()
        ->for($newerCandidate)
        ->for($campaign)
        ->create([
            'ranking_score' => 90,
            'status' => AssessmentStatus::PendingApproval,
            'needs_manual_review' => true,
            'evaluated_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'ranking_score' => null,
            'status' => AssessmentStatus::Submitted,
            'needs_manual_review' => false,
            'evaluated_at' => null,
            'created_at' => now()->subHours(2),
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.has_ranked_candidates', true)
                ->where('overview.summary.total_ranked', 1)
                ->where('overview.summary.pending_approval', 1)
                ->where('overview.summary.needs_manual_review', 1)
                ->where('overview.summary.average_ranking_score', 90)
                ->where('overview.summary.period_label', 'Last 7 days')
                ->where('overview.summary.changes.total_ranked', 0)
                ->where('overview.summary.changes.average_ranking_score', 12.5)
                ->where('overview.summary.changes.pending_approval', 100)
                ->where('overview.summary.changes.needs_manual_review', 100)
                ->has('overview.charts.ranking_activity', 7)
                ->where('overview.charts.ranking_activity.5.ranked_count', 1)
                ->missing('overview.charts.ranking_activity.5.average_score')
                ->where('overview.charts.score_distribution.2.count', 0)
                ->where('overview.charts.score_distribution.3.count', 1)
                ->where('overview.needs_attention.summary.campaigns', 1)
                ->where('overview.needs_attention.summary.pending', 1)
                ->where('overview.needs_attention.summary.manual_reviews', 1)
                ->where('overview.needs_attention.summary.failures', 0)
                ->has('overview.needs_attention.items', 1)
                ->where('overview.needs_attention.items.0.campaign_id', $campaign->id)
                ->where('overview.needs_attention.items.0.label', 'Dashboard Campaign')
                ->where('overview.needs_attention.items.0.badge', '1 pending'),
            ),
        );
});

test('admin dashboard needs attention prioritizes failures then pending then manual review and limits items', function () {
    $admin = User::factory()->teamOwner()->create();

    $manualOnly = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Manual Review Campaign',
    ]);
    $pending = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Pending Campaign',
    ]);
    $failed = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Failed Campaign',
    ]);
    $extraPending = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Extra Pending Campaign',
    ]);
    $quiet = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Quiet Campaign',
    ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($manualOnly)
        ->create([
            'ranking_score' => 70,
            'status' => AssessmentStatus::Evaluated,
            'needs_manual_review' => true,
            'evaluated_at' => now()->subDay(),
        ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($pending)
        ->create([
            'ranking_score' => 85,
            'status' => AssessmentStatus::PendingApproval,
            'needs_manual_review' => false,
            'evaluated_at' => now()->subDay(),
        ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($failed)
        ->create([
            'ranking_score' => null,
            'status' => AssessmentStatus::EvaluationFailed,
            'needs_manual_review' => false,
            'evaluated_at' => null,
            'created_at' => now()->subDay(),
        ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($extraPending)
        ->create([
            'ranking_score' => 88,
            'status' => AssessmentStatus::PendingApproval,
            'needs_manual_review' => false,
            'evaluated_at' => now()->subDay(),
        ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($quiet)
        ->create([
            'ranking_score' => 75,
            'status' => AssessmentStatus::Evaluated,
            'needs_manual_review' => false,
            'evaluated_at' => now()->subDay(),
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.needs_attention.summary.campaigns', 4)
                ->where('overview.needs_attention.summary.pending', 2)
                ->where('overview.needs_attention.summary.manual_reviews', 1)
                ->where('overview.needs_attention.summary.failures', 1)
                ->has('overview.needs_attention.items', 3)
                ->where('overview.needs_attention.items.0.campaign_id', $failed->id)
                ->where('overview.needs_attention.items.0.badge', '1 failure')
                ->where('overview.needs_attention.items.1.campaign_id', $pending->id)
                ->where('overview.needs_attention.items.1.badge', '1 pending')
                ->where('overview.needs_attention.items.2.campaign_id', $extraPending->id)
                ->where('overview.needs_attention.items.2.badge', '1 pending'),
            ),
        );
});

test('admin dashboard reports when ranked candidates exist but none in the current period', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->active()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
    ]);

    Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'ranking_score' => 80,
            'status' => AssessmentStatus::Evaluated,
            'needs_manual_review' => false,
            'evaluated_at' => now()->subDays(20),
            'created_at' => now()->subDays(20),
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.has_ranked_candidates', true)
                ->where('overview.summary.total_ranked', 0)
                ->where('overview.summary.average_ranking_score', null)
                ->where('overview.charts.ranking_activity.0.ranked_count', 0)
                ->where('overview.charts.score_distribution.0.count', 0),
            ),
        );
});

test('admin dashboard reports when no candidates have ever been ranked', function () {
    $admin = User::factory()->teamOwner()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.has_ranked_candidates', false)
                ->where('overview.summary.total_ranked', 0),
            ),
        );
});

test('non-admin dashboard does not include ranking overview', function () {
    $candidate = User::factory()->create([
        'google_id' => 'google-candidate-123',
    ]);

    $this->actingAs($candidate)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview', null),
        );
});
