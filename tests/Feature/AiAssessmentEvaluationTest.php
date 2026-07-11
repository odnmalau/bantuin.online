<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Ai\AssessmentEvaluationException;
use App\Services\Ai\AssessmentEvaluationResult;
use App\Services\Ai\QwenAssessmentEvaluator;
use App\Services\AssessmentEvaluationPipeline;
use App\Services\AssessmentThreshold;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->withoutVite();
    config()->set('assessment.threshold', 75);
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

test('evaluation result parses valid structured output', function () {
    $result = AssessmentEvaluationResult::fromStructuredOutput([
        'score' => 85,
        'justification' => 'Strong answer.',
        'email' => [
            'subject' => 'Interview invitation',
            'body' => 'Please continue to the interview stage.',
        ],
    ], 75);

    expect($result)
        ->score->toBe(85)
        ->justification->toBe('Strong answer.')
        ->emailSubject->toBe('Interview invitation')
        ->emailBody->toBe('Please continue to the interview stage.');
});

test('evaluation result rejects invalid score', function () {
    expect(fn () => AssessmentEvaluationResult::fromStructuredOutput([
        'score' => 101,
        'justification' => 'Invalid high score.',
    ], 75))->toThrow(AssessmentEvaluationException::class);
});

test('evaluation result requires email draft when score meets threshold', function () {
    expect(fn () => AssessmentEvaluationResult::fromStructuredOutput([
        'score' => 80,
        'justification' => 'Passing answer without email draft.',
    ], 75))->toThrow(AssessmentEvaluationException::class);
});

test('evaluation job marks passing score as pending approval', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answers are sufficiently detailed and align with the provided rubrics.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::Submitted,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('This answer explains the tradeoffs clearly. ', 4),
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_score->toBe(82)
        ->ai_justification->not->toBeNull()
        ->ai_email_subject->not->toBeNull()
        ->ai_email_body->not->toBeNull()
        ->evaluated_at->not->toBeNull();

    AssessmentEvaluatorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Explain indexes.'));
});

test('evaluation job does not mutate an assessment after team deactivation', function () {
    $campaign = Campaign::factory()->create();
    $assessment = Assessment::factory()->for($campaign)->create([
        'status' => AssessmentStatus::Submitted,
    ]);
    $job = new EvaluateAssessmentWithAi($assessment);
    $assessment->campaign->team->update([
        'status' => 'deactivated',
        'deactivated_at' => now(),
    ]);

    app()->call([$job, 'handle']);

    expect($assessment->fresh())
        ->status->toBe(AssessmentStatus::Submitted)
        ->ai_score->toBeNull();
});

test('assessment evaluation pipeline marks passing score as pending approval', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answers are sufficiently detailed and align with the provided rubrics.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::Submitted,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('This answer explains the tradeoffs clearly. ', 4),
                ],
            ],
        ]);

    $evaluated = app(AssessmentEvaluationPipeline::class)->evaluate($assessment);

    expect($evaluated)
        ->not->toBeNull()
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_score->toBe(82)
        ->ai_justification->not->toBeNull()
        ->ai_email_subject->not->toBeNull()
        ->ai_email_body->not->toBeNull()
        ->evaluated_at->not->toBeNull();

    expect($evaluated->events()->pluck('type')->all())
        ->toContain('evaluation_started')
        ->toContain('qwen_essay_evaluation_completed')
        ->toContain('deterministic_grading_completed')
        ->toContain('ranking_calculated')
        ->toContain('critic_completed')
        ->toContain('draft_email_generated')
        ->toContain('evaluation_completed');

    AssessmentEvaluatorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Explain indexes.'));
});

test('qwen evaluator repairs invalid structured output with a follow up prompt', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => '84',
            'justification' => 'Strong answer with invalid score type.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
        [
            'score' => 84,
            'justification' => 'Strong answer after repair.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => 'Indexes speed up reads but cost storage and slower writes.',
                ],
            ],
        ]);

    $result = app(QwenAssessmentEvaluator::class)->evaluate($assessment);

    expect($result)
        ->score->toBe(84)
        ->justification->toBe('Strong answer after repair.');

    AssessmentEvaluatorAgent::assertPrompted(
        fn ($prompt): bool => str_contains($prompt->prompt, 'failed backend validation'),
    );
});

test('qwen evaluator fails when repair attempts are exhausted', function () {
    config()->set('assessment.evaluation.repair_attempts', 1);

    AssessmentEvaluatorAgent::fake([
        [
            'score' => '84',
            'justification' => 'Invalid score type.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
        [
            'score' => 101,
            'justification' => 'Still invalid after repair.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => 'Indexes speed up reads but cost storage and slower writes.',
                ],
            ],
        ]);

    expect(fn () => app(QwenAssessmentEvaluator::class)->evaluate($assessment))
        ->toThrow(AssessmentEvaluationException::class, 'score must be between 0 and 100');
});

test('qwen evaluator sends prompt through laravel ai sdk qwen provider', function () {
    config()->set('ai.providers.qwen.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');

    Http::fake([
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'score' => 88,
                            'justification' => 'The candidate gives a strong assessment answer.',
                            'email' => [
                                'subject' => 'Interview Invitation - Candidate',
                                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
                            ],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 120,
                'completion_tokens' => 80,
            ],
        ]),
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate()->state([
            'name' => 'Candidate One',
            'email' => 'candidate-one@example.com',
        ]))
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => 'Indexes speed up reads but cost storage and slower writes.',
                ],
            ],
        ]);

    $result = app(QwenAssessmentEvaluator::class)->evaluate($assessment);

    expect($result)
        ->score->toBe(88)
        ->justification->toBe('The candidate gives a strong assessment answer.')
        ->emailSubject->toBe('Interview Invitation - Candidate');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
        && $request['model'] === 'qwen3.7-plus'
        && $request->hasHeader('Authorization', 'Bearer test-qwen-key')
        && data_get($request->data(), 'response_format.type') === 'json_object'
        && data_get($request->data(), 'enable_thinking') === false
        && ! array_key_exists('max_tokens', $request->data())
        && str_contains(data_get($request->data(), 'messages.0.content'), 'JSON')
        && str_contains(data_get($request->data(), 'messages.1.content'), 'Explain indexes.'));
});

test('evaluation job marks low score as evaluated for manual review', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 60,
            'justification' => 'The answers are too brief or incomplete against the provided rubrics.',
            'email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain dependency injection.',
                    'rubric' => 'Mentions inversion of control and testability.',
                    'answer' => 'It helps.',
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Evaluated)
        ->ai_score->toBe(60)
        ->ai_email_subject->toBeNull()
        ->ai_email_body->toBeNull()
        ->evaluated_at->not->toBeNull();
});

test('evaluation job uses threshold config to determine review status', function () {
    config()->set('assessment.threshold', 90);

    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answers are solid but below the configured threshold.',
            'email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('This answer explains the tradeoffs clearly. ', 4),
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::Evaluated)
        ->ai_score->toBe(82)
        ->ai_email_subject->toBeNull()
        ->ai_email_body->toBeNull();
});

test('evaluation job uses campaign threshold when assessment belongs to a campaign', function () {
    config()->set('assessment.threshold', 90);

    AssessmentEvaluatorAgent::fake([
        [
            'score' => 75,
            'justification' => 'Solid answers that meet the campaign threshold.',
            'email' => [
                'subject' => 'Interview invitation',
                'body' => 'Please continue to the interview stage.',
            ],
        ],
    ]);

    $campaign = Campaign::factory()->create([
        'threshold_score' => 70,
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->for($campaign)
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('This answer explains the tradeoffs clearly. ', 4),
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_score->toBe(75)
        ->ai_email_subject->toBe('Interview invitation')
        ->ai_email_body->toBe('Please continue to the interview stage.');
});

test('evaluation job marks evaluator failure as evaluation failed', function () {
    Log::spy();

    app()->instance(QwenAssessmentEvaluator::class, new class(app(AssessmentThreshold::class)) extends QwenAssessmentEvaluator
    {
        public function evaluate(Assessment $assessment): AssessmentEvaluationResult
        {
            throw new AssessmentEvaluationException('Fake evaluator failure.');
        }
    });

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create();

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::EvaluationFailed)
        ->ai_score->toBeNull()
        ->evaluated_at->toBeNull();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Assessment AI evaluation failed.', Mockery::on(fn (array $context): bool => $context['assessment_id'] === $assessment->id
            && $context['candidate_id'] === $assessment->user_id
            && $context['exception'] === AssessmentEvaluationException::class));
});

test('qwen secret is not included in prompt payload or candidate response', function () {
    config()->set('ai.providers.qwen.key', 'secret-qwen-token');

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->create();
    $assessment = Assessment::factory()
        ->for($candidate)
        ->for($campaign)
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'ai_score' => 82,
            'ai_justification' => 'Safe justification.',
        ]);

    $payload = app(QwenAssessmentEvaluator::class)->promptPayload($assessment);
    assignCandidateToCampaignExam($candidate, $assessment->campaign);

    expect(json_encode($payload))->not->toContain('secret-qwen-token');

    $this->actingAs($candidate)
        ->get(route('candidate.assessments.show', $assessment))
        ->assertOk()
        ->assertDontSee('secret-qwen-token', false);
});

test('evaluation job records that processing started while in evaluating status', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'Strong answer.',
            'email' => [
                'subject' => 'Interview Invitation',
                'body' => 'Thank you for completing the assessment.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::Submitted,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions tradeoffs.',
                    'answer' => 'Indexes help reads.',
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    expect($assessment->refresh()->events()->pluck('type')->all())
        ->toContain('evaluation_started');
});
