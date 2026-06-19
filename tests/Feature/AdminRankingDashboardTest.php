<?php

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view candidate ranking dashboard ordered by ranking score', function () {
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
            'ranking_payload' => [
                'section_scores' => [],
            ],
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
            'resume_payload' => [
                'matched_skills' => ['Laravel', 'PostgreSQL'],
                'missing_skills' => ['Kubernetes'],
                'interview_probes' => ['Ask about queue retries.'],
            ],
            'ranking_payload' => [
                'section_scores' => [
                    [
                        'section_id' => 10,
                        'title' => 'Knowledge Check',
                        'weight' => 100,
                        'earned_points' => 10,
                        'total_points' => 10,
                        'score' => 100,
                    ],
                ],
            ],
        ]);

    $this->actingAs($admin)
        ->get(route('admin.rankings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rankings/index')
            ->where('summary.total_ranked', 2)
            ->where('summary.pending_approval', 1)
            ->where('rankings.0.assessment_id', $topAssessment->id)
            ->where('rankings.0.rank', 1)
            ->where('rankings.0.candidate_name', 'Top Candidate')
            ->where('rankings.0.campaign_title', 'Backend Hiring')
            ->where('rankings.0.role_title', 'Backend Engineer')
            ->where('rankings.0.ranking_score', 91)
            ->where('rankings.0.matched_skills.0', 'Laravel')
            ->where('rankings.0.section_scores.0.title', 'Knowledge Check')
            ->where('rankings.1.assessment_id', $secondAssessment->id),
        );
});

test('candidate cannot access candidate ranking dashboard', function () {
    $candidate = User::factory()->candidate()->create();

    $this->actingAs($candidate)
        ->get(route('admin.rankings.index'))
        ->assertForbidden();
});
