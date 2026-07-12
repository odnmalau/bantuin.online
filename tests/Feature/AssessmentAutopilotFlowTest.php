<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
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
use App\QuestionType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('assessment.threshold', 75);
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

test('assessment autopilot product flow works end to end', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answer meets the rubric and identifies the important tradeoffs.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate One',
                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
            ],
        ],
    ]);

    $admin = User::factory()->teamOwner()->create();
    $candidate = User::factory()->create([
        'name' => 'Candidate One',
        'email' => 'candidate-one@example.com',
    ]);

    $campaign = Campaign::factory()->for($admin, 'creator')->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::LongText,
            'prompt' => 'Explain database indexes.',
            'expected_rubric' => 'Mentions read performance, write cost, and storage tradeoffs.',
            'sort_order' => 1,
        ]);

    Bus::fake();
    Storage::fake('local');

    submitCandidateAssessmentViaExamSession($candidate, $campaign, [
        $question->id => str_repeat('Indexes speed reads while adding write and storage tradeoffs. ', 3),
    ], resumePdfUpload('Laravel database indexing experience'));

    $assessment = Assessment::query()->whereBelongsTo($candidate)->sole();

    Bus::assertChained([
        ScreenResumeWithAi::class,
        EvaluateAssessmentWithAi::class,
    ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_score->toBe(82)
        ->ai_email_subject->not->toBeNull()
        ->ai_email_body->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.assessments.approve', $assessment), [
            'email_subject' => 'Final interview invitation',
            'email_body' => 'Final email body approved by Admin.',
        ])
        ->assertRedirect(route('admin.assessments.show', $assessment));

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Approved)
        ->approved_email_subject->toBe('Final interview invitation')
        ->approved_email_body->toBe('Final email body approved by Admin.');

    Bus::assertDispatched(SendInterviewInvitationEmail::class);

    Mail::fake();

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertSent(InterviewInvitationMail::class, 'candidate-one@example.com');

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailSent)
        ->email_sent_at->not->toBeNull();
});
