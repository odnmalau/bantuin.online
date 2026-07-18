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
    fakeAssessmentCriticReasoning();
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

    fakeAssessmentEvaluationReasoning();

    AssessmentEvaluatorAgent::fake([
        assessmentEvaluationResponse(82, [$question->id]),
    ]);

    Bus::fake();
    Storage::fake('r2-private');

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
        ->status->toBe(AssessmentStatus::Approved)
        ->assessment_score->toBe(82)
        ->ai_email_subject->not->toBeNull()
        ->ai_email_body->not->toBeNull()
        ->approved_email_subject->toBe('Interview Invitation')
        ->approved_email_body->not->toBeNull();

    Bus::assertDispatched(SendInterviewInvitationEmail::class);

    Mail::fake();

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertSent(InterviewInvitationMail::class, 'candidate-one@example.com');

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailSent)
        ->email_sent_at->not->toBeNull();
});
