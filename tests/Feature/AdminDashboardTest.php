<?php

use App\AssessmentStatus;
use App\CampaignStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin dashboard includes ranking overview summary and charts', function () {
    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->create();
    $olderCandidate = User::factory()->candidate()->create();
    $newerCandidate = User::factory()->candidate()->create();

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

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.summary.total_ranked', 2)
                ->where('overview.summary.pending_approval', 1)
                ->where('overview.summary.needs_manual_review', 1)
                ->where('overview.summary.average_ranking_score', 85)
                ->where('overview.summary.period_label', 'Last 7 days')
                ->where('overview.summary.changes.total_ranked', 100)
                ->where('overview.summary.changes.pending_approval', 100)
                ->where('overview.summary.changes.needs_manual_review', 100)
                ->has('overview.charts.average_score_trend', 7)
                ->where('overview.charts.average_score_trend.5.average_score', 90)
                ->where('overview.charts.average_score_trend.5.ranked_count', 1)
                ->where('overview.charts.score_distribution.2.count', 1)
                ->where('overview.charts.score_distribution.3.count', 1),
            ),
        );
});

test('admin dashboard includes attention-first recent campaigns', function () {
    $admin = User::factory()->admin()->create();

    $draft = Campaign::factory()->create([
        'title' => 'Draft Campaign',
        'status' => CampaignStatus::Draft,
        'updated_at' => now()->subHour(),
    ]);

    $questionReview = Campaign::factory()->create([
        'title' => 'Question Review Campaign',
        'status' => CampaignStatus::QuestionReview,
        'updated_at' => now()->subMinutes(30),
    ]);

    $quietActive = Campaign::factory()->active()->create([
        'title' => 'Quiet Active Campaign',
        'updated_at' => now()->subMinutes(10),
    ]);

    $attentionActive = Campaign::factory()->active()->create([
        'title' => 'Attention Active Campaign',
        'updated_at' => now()->subMinutes(5),
    ]);

    Campaign::factory()->create([
        'title' => 'Archived Campaign',
        'status' => CampaignStatus::Archived,
        'updated_at' => now(),
    ]);

    Assessment::factory()
        ->for(User::factory()->candidate())
        ->for($attentionActive)
        ->create([
            'ranking_score' => 88,
            'status' => AssessmentStatus::PendingApproval,
            'needs_manual_review' => true,
        ]);

    Assessment::factory()
        ->for(User::factory()->candidate())
        ->for($quietActive)
        ->create([
            'ranking_score' => 70,
            'status' => AssessmentStatus::Evaluated,
            'needs_manual_review' => false,
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('overview.recent_campaigns', 4)
                ->where('overview.recent_campaigns.0.id', $attentionActive->id)
                ->where('overview.recent_campaigns.0.title', 'Attention Active Campaign')
                ->where('overview.recent_campaigns.0.status', 'active')
                ->where('overview.recent_campaigns.0.status_label', 'Active')
                ->where('overview.recent_campaigns.0.pending_approval_count', 1)
                ->where('overview.recent_campaigns.0.needs_manual_review_count', 1)
                ->where('overview.recent_campaigns.0.ranked_count', 1)
                ->where('overview.recent_campaigns.1.id', $questionReview->id)
                ->where('overview.recent_campaigns.2.id', $draft->id)
                ->where('overview.recent_campaigns.3.id', $quietActive->id)
                ->where('overview.recent_campaigns.3.pending_approval_count', 0)
                ->where('overview.recent_campaigns.3.ranked_count', 1),
            ),
        );
});

test('non-admin dashboard does not include ranking overview', function () {
    $candidate = User::factory()->candidate()->create([
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
