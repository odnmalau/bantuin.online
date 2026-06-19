<?php

use App\Ai\Agents\QuestionBankGeneratorAgent;
use App\Models\QuestionBank;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\QwenQuestionBankGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('ai.providers.qwen.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');
});

test('admin can generate more draft questions for a question library', function () {
    QuestionBankGeneratorAgent::fake([
        generatedQuestionBankOutput(),
    ]);

    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create([
        'title' => 'Laravel Backend Library',
        'skill_area' => 'Laravel',
        'description' => 'Reusable backend screening questions.',
    ]);

    $questionBank->questions()->create([
        'type' => QuestionType::LongText,
        'grading_mode' => QuestionGradingMode::Ai,
        'prompt' => 'Explain queue failure handling in Laravel.',
        'expected_rubric' => 'Mentions retries, dead lettering, and monitoring.',
        'points' => 20,
        'difficulty' => 'medium',
        'skill_tags' => ['Queues'],
        'ai_generated' => false,
        'status' => QuestionStatus::Approved,
        'sort_order' => 10,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.question-banks.show', $questionBank))
        ->post(route('admin.question-banks.generate-questions', $questionBank), [
            'question_count' => 2,
            'language' => 'English',
            'difficulty' => 'mixed',
            'question_mix' => '1 multiple choice and 1 long text question.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.question-banks.show', $questionBank));

    $newQuestions = $questionBank->questions()
        ->where('status', QuestionStatus::Draft->value)
        ->orderBy('sort_order')
        ->get();

    expect($newQuestions)->toHaveCount(2)
        ->and($newQuestions[0])
        ->type->toBe(QuestionType::MultipleChoice)
        ->ai_generated->toBeTrue()
        ->sort_order->toBe(20)
        ->and($newQuestions[1])
        ->type->toBe(QuestionType::LongText)
        ->sort_order->toBe(30);

    $audit = $questionBank->refresh()->ai_generation_audit;

    expect($audit)->toHaveCount(1)
        ->and($audit[0]['questions_created'])->toBe(2)
        ->and($audit[0]['agent'])->toContain('QuestionBankGeneratorAgent');

    QuestionBankGeneratorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Laravel Backend Library')
        && str_contains($prompt->prompt, 'Explain queue failure handling in Laravel.')
        && str_contains($prompt->prompt, 'existing_questions'));
});

test('invalid generated library answer keys are rejected without saving drafts', function () {
    QuestionBankGeneratorAgent::fake([
        [
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
    ]);

    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.question-banks.generate-questions', $questionBank), [
            'question_count' => 1,
            'language' => 'English',
            'difficulty' => 'easy',
        ])
        ->assertSessionHasErrors('generation');

    expect(session('errors')->get('generation')[0])
        ->not->toContain('Invalid assessment generation output');

    expect($questionBank->questions()->count())->toBe(0)
        ->and($questionBank->refresh()->ai_generation_audit)->toBeNull();
});

test('question bank generation appends sort order after existing questions', function () {
    QuestionBankGeneratorAgent::fake([
        generatedQuestionBankOutput(),
    ]);

    $questionBank = QuestionBank::factory()->create();
    $questionBank->questions()->create([
        'type' => QuestionType::ShortText,
        'grading_mode' => QuestionGradingMode::Ai,
        'prompt' => 'Existing question',
        'expected_rubric' => 'Mentions validation.',
        'points' => 10,
        'difficulty' => 'easy',
        'skill_tags' => ['Laravel'],
        'ai_generated' => false,
        'status' => QuestionStatus::Approved,
        'sort_order' => 100,
    ]);

    app(QwenQuestionBankGenerator::class)->generate($questionBank, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
    ]);

    expect($questionBank->questions()->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([100, 110, 120]);
});

test('question bank generation respects question_count limit', function () {
    $output = generatedQuestionBankOutput();
    $output['questions'][] = [
        'type' => QuestionType::ShortText->value,
        'prompt' => 'Extra question that should be dropped.',
        'options' => null,
        'correct_answer' => null,
        'expected_rubric' => 'Mentions validation.',
        'points' => 5,
        'difficulty' => 'easy',
        'skill_tags' => ['Laravel'],
        'sort_order' => 30,
    ];

    QuestionBankGeneratorAgent::fake([$output]);

    $questionBank = QuestionBank::factory()->create();

    app(QwenQuestionBankGenerator::class)->generate($questionBank, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
    ]);

    expect($questionBank->questions()->count())->toBe(2);
});

test('candidate cannot generate question library questions', function () {
    QuestionBankGeneratorAgent::fake([
        generatedQuestionBankOutput(),
    ]);

    $candidate = User::factory()->candidate()->create();
    $questionBank = QuestionBank::factory()->create();

    $this->actingAs($candidate)
        ->post(route('admin.question-banks.generate-questions', $questionBank), [
            'question_count' => 2,
            'language' => 'English',
            'difficulty' => 'mixed',
        ])
        ->assertForbidden();
});

test('qwen question bank generator uses json object mode through the qwen provider', function () {
    Http::fake([
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(generatedQuestionBankOutput()),
                    ],
                ],
            ],
        ]),
    ]);

    $questionBank = QuestionBank::factory()->create([
        'title' => 'PostgreSQL Library',
        'skill_area' => 'PostgreSQL',
    ]);

    app(QwenQuestionBankGenerator::class)->generate($questionBank, [
        'question_count' => 2,
        'language' => 'English',
        'difficulty' => 'mixed',
    ]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
        && data_get($request->data(), 'response_format.type') === 'json_object'
        && str_contains(data_get($request->data(), 'messages.1.content'), 'PostgreSQL Library'));
});

/**
 * @return array<string, mixed>
 */
function generatedQuestionBankOutput(): array
{
    return [
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
    ];
}
