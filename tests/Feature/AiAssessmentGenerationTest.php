<?php

use App\Ai\Agents\AssessmentGeneratorAgent;
use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\AssessmentGenerationException;
use App\Services\Ai\QwenAssessmentGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('ai.providers.qwen.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');
});

test('admin can generate draft assessment questions for a campaign', function () {
    AssessmentGeneratorAgent::fake([
        generatedAssessmentOutput(),
    ]);

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Backend Engineer Screening',
        'role_title' => 'Backend Engineer',
        'job_description' => 'Build Laravel APIs and queue workers.',
        'required_skills' => ['Laravel', 'PostgreSQL', 'Queues'],
    ]);
    $campaign->forceFill([
        'ai_generation_notes' => 'Prefer practical debugging questions.',
    ])->save();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.generate-assessment', $campaign), [
            'question_count' => 2,
            'language' => 'English',
            'difficulty' => 'mixed',
            'question_mix' => '1 multiple choice and 1 long text question.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $section = $campaign->sections()->where('title', 'Backend Fundamentals')->sole();
    $questions = $section->questions()->orderBy('sort_order')->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0])
        ->type->toBe(QuestionType::MultipleChoice)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->status->toBe(QuestionStatus::Draft)
        ->ai_generated->toBeTrue()
        ->options->toBe(['database', 'sync', 'redis', 'sqs'])
        ->correct_answer->toBe(['database'])
        ->and($questions[1])
        ->type->toBe(QuestionType::LongText)
        ->grading_mode->toBe(QuestionGradingMode::Ai)
        ->status->toBe(QuestionStatus::Draft)
        ->expected_rubric->toContain('logs');

    expect($campaign->refresh())
        ->status->toBe(CampaignStatus::QuestionReview)
        ->activated_at->toBeNull();

    $audit = $campaign->ai_generation_audit;

    expect($audit)->toHaveCount(1)
        ->and($audit[0]['provider'])->toBe('qwen')
        ->and($audit[0]['model'])->toBe('qwen3.7-plus')
        ->and($audit[0]['prompt_version'])->toBe('1')
        ->and($audit[0]['agent'])->toContain('AssessmentGeneratorAgent')
        ->and($audit[0]['questions_created'])->toBe(2)
        ->and($audit[0]['sections_created'])->toBe(1)
        ->and($audit[0]['generation_options']['question_count'])->toBe(2)
        ->and($audit[0]['generation_options']['language'])->toBe('English')
        ->and($audit[0]['generation_options']['difficulty'])->toBe('mixed')
        ->and($audit[0]['generation_options']['question_mix'])->toContain('multiple choice')
        ->and($audit[0])->not->toHaveKey('api_key');

    AssessmentGeneratorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Backend Engineer')
        && str_contains($prompt->prompt, 'Laravel')
        && str_contains($prompt->prompt, 'multiple choice')
        && str_contains($prompt->prompt, 'ai_generation_notes')
        && ! str_contains($prompt->prompt, 'generation_instructions')
        && str_contains($prompt->prompt, 'Prioritize job-relevant scenarios over trivia')
        && ! str_contains($prompt->prompt, 'Prefer practical debugging questions.'));
});

test('campaign assessment generation respects question_count limit', function () {
    $output = generatedAssessmentOutput();
    $output['sections'][0]['questions'][] = [
        'type' => QuestionType::ShortText->value,
        'prompt' => 'Describe Laravel service container bindings.',
        'options' => null,
        'correct_answer' => null,
        'expected_rubric' => 'Mentions bindings, resolution, and lifecycle.',
        'points' => 10,
        'difficulty' => 'medium',
        'skill_tags' => ['Laravel'],
        'sort_order' => 30,
    ];

    AssessmentGeneratorAgent::fake([$output]);

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.generate-assessment', $campaign), [
            'question_count' => 1,
            'language' => 'English',
            'difficulty' => 'mixed',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->questions()->count())->toBe(1);
});

test('invalid generated answer keys are rejected without saving drafts', function () {
    AssessmentGeneratorAgent::fake([
        [
            'sections' => [
                [
                    'title' => 'Invalid Section',
                    'description' => null,
                    'duration_minutes' => 20,
                    'scoring_mode' => 'weighted',
                    'weight' => 100,
                    'sort_order' => 10,
                    'questions' => [
                        [
                            'type' => QuestionType::MultipleChoice->value,
                            'prompt' => 'Which driver is configured?',
                            'options' => ['database', 'sync'],
                            'correct_answer' => null,
                            'expected_rubric' => null,
                            'points' => 10,
                            'difficulty' => 'easy',
                            'skill_tags' => ['Queues'],
                            'sort_order' => 10,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.generate-assessment', $campaign), [
            'question_count' => 1,
            'language' => 'English',
            'difficulty' => 'easy',
        ])
        ->assertSessionHasErrors('generation')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->sections()->exists())->toBeFalse()
        ->and($campaign->questions()->exists())->toBeFalse()
        ->and($campaign->refresh()->ai_generation_audit)->toBeNull();
});

test('qwen assessment generator uses json object mode through the qwen provider', function () {
    Http::fake([
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(generatedAssessmentOutput()),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 220,
                'completion_tokens' => 180,
            ],
        ]),
    ]);

    $campaign = Campaign::factory()->create([
        'role_title' => 'Backend Engineer',
        'required_skills' => ['Laravel', 'PostgreSQL'],
    ]);

    $createdQuestions = app(QwenAssessmentGenerator::class)->generate($campaign, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
        'question_mix' => 'Balanced question set.',
    ]);

    expect($createdQuestions)->toBe(2)
        ->and($campaign->questions()->where('status', QuestionStatus::Draft->value)->count())->toBe(2)
        ->and($campaign->refresh()->ai_generation_audit)->toHaveCount(1);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
        && $request['model'] === 'qwen3.7-plus'
        && $request->hasHeader('Authorization', 'Bearer test-qwen-key')
        && data_get($request->data(), 'response_format.type') === 'json_object'
        && data_get($request->data(), 'enable_thinking') === false
        && ! array_key_exists('max_tokens', $request->data())
        && str_contains(data_get($request->data(), 'messages.0.content'), 'JSON')
        && str_contains(data_get($request->data(), 'messages.1.content'), 'Backend Engineer'));
});

test('assessment generation appends audit metadata for each successful run', function () {
    AssessmentGeneratorAgent::fake([
        generatedAssessmentOutput(),
        generatedAssessmentOutput(),
    ]);

    $campaign = Campaign::factory()->create();
    $generator = app(QwenAssessmentGenerator::class);

    config()->set('assessment.generator.prompt_version', '2');

    $generator->generate($campaign, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
    ]);

    $generator->generate($campaign->fresh(), [
        'question_count' => 2,
        'language' => 'Indonesian',
        'difficulty' => 'easy',
    ]);

    $audit = $campaign->fresh()->ai_generation_audit;

    expect($audit)->toHaveCount(2)
        ->and($audit[0]['prompt_version'])->toBe('2')
        ->and($audit[0]['generation_options']['language'])->toBe('English')
        ->and($audit[1]['generation_options']['language'])->toBe('Indonesian')
        ->and($audit[1]['questions_created'])->toBe(2);
});

test('candidate cannot generate campaign assessments', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.generate-assessment', $campaign), [
            'question_count' => 2,
            'language' => 'English',
            'difficulty' => 'mixed',
        ])
        ->assertForbidden();
});

test('assessment generation rechecks that the campaign team is writable', function () {
    AssessmentGeneratorAgent::fake([generatedAssessmentOutput()]);
    $campaign = Campaign::factory()->create();
    $campaign->team->update(['status' => 'deactivated', 'deactivated_at' => now()]);

    expect(fn () => app(QwenAssessmentGenerator::class)->generate($campaign, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
    ]))->toThrow(AssessmentGenerationException::class);

    AssessmentGeneratorAgent::assertNeverPrompted();
    expect($campaign->questions()->exists())->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function generatedAssessmentOutput(): array
{
    return [
        'sections' => [
            [
                'title' => 'Backend Fundamentals',
                'description' => 'Core backend screening questions.',
                'duration_minutes' => 30,
                'scoring_mode' => 'weighted',
                'weight' => 100,
                'sort_order' => 10,
                'questions' => [
                    [
                        'type' => QuestionType::MultipleChoice->value,
                        'prompt' => 'Which queue driver is configured for HirePilot?',
                        'options' => ['database', 'sync', 'redis', 'sqs'],
                        'correct_answer' => ['database'],
                        'expected_rubric' => null,
                        'points' => 10,
                        'difficulty' => 'easy',
                        'skill_tags' => ['Queues'],
                        'sort_order' => 10,
                    ],
                    [
                        'type' => QuestionType::LongText->value,
                        'prompt' => 'Explain how you would debug a slow Laravel API endpoint.',
                        'options' => null,
                        'correct_answer' => null,
                        'expected_rubric' => 'Mentions logs, metrics, database query inspection, N+1 checks, and verification.',
                        'points' => 20,
                        'difficulty' => 'medium',
                        'skill_tags' => ['Laravel', 'Debugging'],
                        'sort_order' => 20,
                    ],
                ],
            ],
        ],
    ];
}
