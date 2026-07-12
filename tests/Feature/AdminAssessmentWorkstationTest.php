<?php

use App\AssessmentStatus;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view assessment detail', function () {
    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'team_id' => $admin->current_team_id,
        'created_by' => $admin->id,
        'title' => 'Backend Hiring',
        'role_title' => 'Backend Engineer',
    ]);
    $assessment = Assessment::factory()
        ->for($candidate)
        ->for($campaign)
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 7,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions read and write tradeoffs.',
                    'answer' => 'Indexes speed reads and add write costs.',
                ],
            ],
            'ai_score' => 82,
            'ranking_score' => 91,
            'ai_justification' => 'Strong enough for interview.',
            'ai_email_subject' => 'Interview invitation',
            'ai_email_body' => 'Please continue to interview.',
            'status' => AssessmentStatus::PendingApproval,
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

    Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'ranking_score' => 72,
            'status' => AssessmentStatus::Evaluated,
        ]);

    $this->actingAs($admin)
        ->get(route('admin.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assessments/show')
            ->where('assessment.id', $assessment->id)
            ->where('assessment.ai_score', 82)
            ->where('assessment.rank', 1)
            ->where('assessment.campaign.title', 'Backend Hiring')
            ->where('assessment.campaign.role_title', 'Backend Engineer')
            ->where('assessment.section_scores.0.title', 'Knowledge Check')
            ->where('assessment.section_scores.0.score', 100)
            ->where('assessment.ai_justification', 'Strong enough for interview.')
            ->where('assessment.answers_payload.0.question', 'Explain indexes.')
            ->where('assessment.answers_payload.0.rubric', 'Mentions read and write tradeoffs.')
            ->where('assessment.answers_payload.0.answer', 'Indexes speed reads and add write costs.')
            ->where('assessment.can_review', true),
        );
});

test('admin can approve reviewable assessment', function (AssessmentStatus $status) {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => $status,
            'ai_score' => $status === AssessmentStatus::PendingApproval ? 82 : 60,
            'ai_email_subject' => 'AI subject',
            'ai_email_body' => 'AI body',
        ]);
    Queue::fake();

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.approve', $assessment), [
            'email_subject' => 'Final subject from admin',
            'email_body' => 'Final body from admin.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Approved)
        ->approved_email_subject->toBe('Final subject from admin')
        ->approved_email_body->toBe('Final body from admin.')
        ->approved_by->toBe($admin->id)
        ->approved_at->not->toBeNull();

    Queue::assertPushed(SendInterviewInvitationEmail::class, fn (SendInterviewInvitationEmail $job) => $job->assessment->is($assessment));
})->with([
    AssessmentStatus::PendingApproval,
    AssessmentStatus::Evaluated,
    AssessmentStatus::NeedsManualReview,
    AssessmentStatus::Overridden,
]);

test('admin can reject reviewable assessment', function (AssessmentStatus $status) {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => $status,
        ]);

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.reject', $assessment), [
            'reason' => 'Candidate did not meet the assessment bar.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Rejected)
        ->rejected_at->not->toBeNull();
})->with([
    AssessmentStatus::PendingApproval,
    AssessmentStatus::Evaluated,
    AssessmentStatus::NeedsManualReview,
    AssessmentStatus::Overridden,
]);

test('candidate cannot access assessment review actions', function (string $route, string $method) {
    $candidate = User::factory()->create();
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'status' => AssessmentStatus::PendingApproval,
        ]);

    $this->actingAs($candidate)
        ->call($method, route($route, $assessment))
        ->assertForbidden();
})->with([
    ['admin.assessments.show', 'GET'],
    ['admin.assessments.approve', 'POST'],
    ['admin.assessments.reject', 'POST'],
]);

test('approve and reject are rejected for invalid statuses', function (AssessmentStatus $status, string $route) {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => $status,
        ]);
    Queue::fake();

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route($route, $assessment), [
            'email_subject' => 'Final subject',
            'email_body' => 'Final body.',
            'reason' => 'Invalid status transition test.',
        ])
        ->assertSessionHasErrors('assessment')
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh()->status)->toBe($status);
    Queue::assertNothingPushed();
})->with([
    [AssessmentStatus::Submitted, 'admin.assessments.approve'],
    [AssessmentStatus::Approved, 'admin.assessments.approve'],
    [AssessmentStatus::Rejected, 'admin.assessments.reject'],
    [AssessmentStatus::EmailSent, 'admin.assessments.reject'],
]);

test('approve validation requires final email subject and body', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id]);
    $assessment = Assessment::factory()
        ->for(User::factory())
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
        ]);
    Queue::fake();

    $this->actingAs($admin)
        ->from(route('admin.assessments.show', $assessment))
        ->post(route('admin.assessments.approve', $assessment), [
            'email_subject' => '',
            'email_body' => '',
        ])
        ->assertSessionHasErrors(['email_subject', 'email_body'])
        ->assertRedirect(route('admin.assessments.show', $assessment));

    Queue::assertNothingPushed();
});
