<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\Ai\Agents\ResumeScreeningAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Mail\InterviewInvitationMail;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\AssessmentEventRecorder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config()->set('assessment.threshold', 75);
    config()->set('assessment.qwen.provider', 'qwen');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    AssessmentCriticAgent::fake([
        [
            'outcome' => 'passed',
            'summary' => 'Assessment package is consistent and safe for review.',
            'findings' => [],
            'manual_review_required' => false,
            'repaired_email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);
});

test('assessment event recorder redacts sensitive payload keys', function () {
    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create();

    $event = app(AssessmentEventRecorder::class)->record(
        assessment: $assessment,
        type: 'audit_test',
        title: 'Audit payload test',
        payload: [
            'api_key' => 'secret-qwen-token',
            'score' => 82,
            'prompt' => 'Raw AI prompt that should never be stored.',
            'instructions' => 'System instructions for the AI provider.',
            'messages' => [
                ['role' => 'system', 'content' => 'Sensitive system message.'],
            ],
            'nested' => [
                'password' => 'candidate-password',
                'original_context' => [
                    'question' => 'Sensitive question payload.',
                ],
                'invalid_output' => [
                    'body' => 'Sensitive invalid model output.',
                ],
                'safe' => 'visible',
            ],
        ],
    );

    expect($event->payload)
        ->toMatchArray([
            'api_key' => '[redacted]',
            'score' => 82,
            'prompt' => '[redacted]',
            'instructions' => '[redacted]',
            'messages' => '[redacted]',
            'nested' => [
                'password' => '[redacted]',
                'original_context' => '[redacted]',
                'invalid_output' => '[redacted]',
                'safe' => 'visible',
            ],
        ]);
});

test('evaluation job records agent activity timeline events', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answer meets the rubric and identifies important tradeoffs.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'resume_score' => 84,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('Indexes speed reads while adding write and storage tradeoffs. ', 3),
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    expect($assessment->refresh()->events()->pluck('type')->all())
        ->toContain(
            'evaluation_started',
            'qwen_essay_evaluation_completed',
            'deterministic_grading_completed',
            'ranking_calculated',
            'critic_completed',
            'draft_email_generated',
            'evaluation_completed',
        );
});

test('admin approval and email job record human and delivery events', function () {
    Queue::fake();
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $candidate = User::factory()->candidate()->create([
        'email' => 'candidate@example.com',
    ]);
    $assessment = Assessment::factory()
        ->for($candidate)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'ai_email_subject' => 'AI subject',
            'ai_email_body' => 'AI body',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.assessments.approve', $assessment), [
            'email_subject' => 'Final subject from Admin',
            'email_body' => 'Final body from Admin.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh()->events)
        ->sequence(fn ($event) => $event
            ->type->toBe('admin_approved')
            ->actor_id->toBe($admin->id));

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertSent(InterviewInvitationMail::class, 'candidate@example.com');

    expect($assessment->refresh()->events()->pluck('type')->all())
        ->toContain('admin_approved', 'email_sent');
});

test('admin can view timeline and audit panel while candidate cannot see internal events', function () {
    $admin = User::factory()->admin()->create();
    $candidate = User::factory()->candidate()->create();
    $assessment = Assessment::factory()
        ->for($candidate)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'ranking_payload' => [
                'formula' => 'resume_score * 0.35 + essay_score * 0.50 + mcq_score * 0.15',
            ],
        ]);

    app(AssessmentEventRecorder::class)->record(
        assessment: $assessment,
        type: 'internal_audit_event',
        title: 'Internal Qwen audit event',
        description: 'Visible only to Admin assessment review.',
        payload: [
            'token' => 'secret-qwen-token',
            'provider' => 'qwen',
        ],
    );

    $this->actingAs($admin)
        ->get(route('admin.assessments.show', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assessments/show')
            ->has('assessment.events', 1)
            ->where('assessment.events.0.type', 'internal_audit_event')
            ->where('assessment.events.0.title', 'Internal Qwen audit event')
            ->where('assessment.events.0.payload.token', '[redacted]')
            ->where('assessment.audit.provider', 'qwen')
            ->where('assessment.audit.model', 'qwen3.7-plus')
            ->where('assessment.audit.threshold', 75),
        );

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertOk()
        ->assertDontSee('Internal Qwen audit event', false)
        ->assertDontSee('secret-qwen-token', false);
});

test('candidate submission and resume screening record timeline events', function () {
    Bus::fake();
    Storage::fake('local');
    config()->set('ai.providers.qwen.key', 'test-qwen-key');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
            'type' => QuestionType::LongText,
        ]);

    submitCandidateAssessmentViaExamSession($candidate, $campaign, [
        $question->id => 'Answer with enough detail for the rubric.',
    ]);

    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    ResumeScreeningAgent::fake([
        [
            'resume_score' => 84,
            'summary' => 'Candidate shows practical Laravel backend experience.',
            'matched_skills' => ['Laravel'],
            'missing_skills' => ['Kubernetes'],
            'risk_flags' => [],
            'interview_probes' => ['Ask about queue failure handling.'],
            'confidence' => 82,
            'justification' => 'Resume evidence aligns with Laravel experience.',
        ],
    ]);

    app()->call([(new ScreenResumeWithAi($assessment)), 'handle']);

    expect($assessment->refresh()->events()->pluck('type')->all())
        ->toContain(
            'candidate_submitted',
            'resume_uploaded',
            'assessment_queued',
            'resume_processing_started',
            'resume_extracted',
            'resume_screened',
        );
});

test('unsendable interview email job records email failed timeline event', function () {
    Log::spy();
    Mail::fake();

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'approved_email_subject' => null,
            'approved_email_body' => null,
        ]);

    (new SendInterviewInvitationEmail($assessment))->handle();

    expect($assessment->refresh()->events()->pluck('type')->all())
        ->toContain('email_failed');
});
