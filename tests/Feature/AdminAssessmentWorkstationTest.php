<?php

use App\AssessmentStatus;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view assessment workstation list', function () {
    $admin = User::factory()->admin()->create();
    $candidate = User::factory()->candidate()->create([
        'name' => 'Candidate One',
        'email' => 'candidate-one@example.com',
    ]);
    $assessment = Assessment::factory()
        ->for($candidate)
        ->create([
            'ai_score' => 82,
            'status' => AssessmentStatus::PendingApproval,
            'evaluated_at' => now(),
        ]);

    $this->actingAs($admin)
        ->get(route('admin.assessments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assessments/index')
            ->has('assessments', 1)
            ->where('assessments.0.id', $assessment->id)
            ->where('assessments.0.candidate_name', 'Candidate One')
            ->where('assessments.0.candidate_email', 'candidate-one@example.com')
            ->where('assessments.0.ai_score', 82)
            ->where('assessments.0.status', AssessmentStatus::PendingApproval->value),
        );
});

test('admin can view assessment detail', function () {
    $admin = User::factory()->admin()->create();
    $candidate = User::factory()->candidate()->create();
    $assessment = Assessment::factory()
        ->for($candidate)
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
            'ai_justification' => 'Strong enough for interview.',
            'ai_email_subject' => 'Interview invitation',
            'ai_email_body' => 'Please continue to interview.',
            'status' => AssessmentStatus::PendingApproval,
        ]);

    $this->actingAs($admin)
        ->get(route('admin.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assessments/show')
            ->where('assessment.id', $assessment->id)
            ->where('assessment.ai_score', 82)
            ->where('assessment.ai_justification', 'Strong enough for interview.')
            ->where('assessment.answers_payload.0.question', 'Explain indexes.')
            ->where('assessment.answers_payload.0.rubric', 'Mentions read and write tradeoffs.')
            ->where('assessment.answers_payload.0.answer', 'Indexes speed reads and add write costs.')
            ->where('assessment.can_review', true),
        );
});

test('admin can approve reviewable assessment', function (AssessmentStatus $status) {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
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
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
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

test('candidate cannot access assessment workstation', function (string $route, string $method) {
    $candidate = User::factory()->candidate()->create();
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::PendingApproval,
        ]);

    $url = match ($route) {
        'admin.assessments.index' => route($route),
        default => route($route, $assessment),
    };

    $this->actingAs($candidate)
        ->call($method, $url)
        ->assertForbidden();
})->with([
    ['admin.assessments.index', 'GET'],
    ['admin.assessments.show', 'GET'],
    ['admin.assessments.approve', 'POST'],
    ['admin.assessments.reject', 'POST'],
]);

test('approve and reject are rejected for invalid statuses', function (AssessmentStatus $status, string $route) {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
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
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
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
