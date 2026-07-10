<?php

use App\Ai\Agents\TextQuestionToMcqConverterAgent;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\QwenTextQuestionToMcqConverter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('ai.providers.qwen.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');
});

test('admin can convert draft long text campaign question to multiple choice', function () {
    TextQuestionToMcqConverterAgent::fake([
        [
            'prompt' => 'Which Laravel component should you inspect first when debugging a slow API endpoint?',
            'options' => ['Logs', 'Mail config', 'Session driver', 'View cache'],
            'correct_answer' => ['Logs'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'role_title' => 'Backend Engineer',
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::LongText,
            'grading_mode' => QuestionGradingMode::Ai,
            'status' => QuestionStatus::Draft,
            'prompt' => 'Explain how you would debug a slow Laravel API endpoint.',
            'expected_rubric' => 'Mentions logs, metrics, and database inspection.',
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $question->refresh();

    expect($question)
        ->type->toBe(QuestionType::MultipleChoice)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->expected_rubric->toBeNull()
        ->ai_generated->toBeTrue()
        ->options->toBe(['Logs', 'Mail config', 'Session driver', 'View cache'])
        ->correct_answer->toBe(['Logs']);

    TextQuestionToMcqConverterAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Explain how you would debug a slow Laravel API endpoint.')
        && str_contains($prompt->prompt, 'expected_rubric'));
});

test('convert to mcq is rejected for approved campaign questions', function () {
    TextQuestionToMcqConverterAgent::fake([
        convertedMcqOutput(),
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::ShortText,
            'grading_mode' => QuestionGradingMode::Ai,
            'status' => QuestionStatus::Approved,
            'expected_rubric' => 'Mentions validation.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question]))
        ->assertSessionHasErrors('conversion');

    expect($question->refresh()->type)->toBe(QuestionType::ShortText);
});

test('convert to mcq is rejected for multiple choice campaign questions', function () {
    TextQuestionToMcqConverterAgent::fake([
        convertedMcqOutput(),
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question]))
        ->assertSessionHasErrors('conversion');
});

test('invalid mcq conversion output leaves question unchanged', function () {
    TextQuestionToMcqConverterAgent::fake([
        [
            'prompt' => 'Converted prompt',
            'options' => ['a', 'b'],
            'correct_answer' => ['missing'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::LongText,
            'grading_mode' => QuestionGradingMode::Ai,
            'status' => QuestionStatus::Draft,
            'prompt' => 'Keep this prompt',
            'expected_rubric' => 'Keep this rubric',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question]))
        ->assertSessionHasErrors('conversion');

    expect($question->refresh())
        ->type->toBe(QuestionType::LongText)
        ->prompt->toBe('Keep this prompt')
        ->expected_rubric->toBe('Keep this rubric');
});

test('candidate cannot convert campaign questions to mcq', function () {
    TextQuestionToMcqConverterAgent::fake([
        convertedMcqOutput(),
    ]);

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::LongText,
            'status' => QuestionStatus::Draft,
            'expected_rubric' => 'Rubric',
        ]);

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.questions.convert-to-mcq', [$campaign, $question]))
        ->assertForbidden();
});

test('qwen text to mcq converter uses json object mode through the qwen provider', function () {
    Http::fake([
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(convertedMcqOutput()),
                    ],
                ],
            ],
        ]),
    ]);

    $campaign = Campaign::factory()->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::ShortText,
            'status' => QuestionStatus::Draft,
            'prompt' => 'Explain middleware.',
            'expected_rubric' => 'Mentions request pipeline.',
        ]);

    app(QwenTextQuestionToMcqConverter::class)->convertCampaignQuestion($question, $campaign);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
        && data_get($request->data(), 'response_format.type') === 'json_object'
        && str_contains(data_get($request->data(), 'messages.1.content'), 'Explain middleware.'));
});

/**
 * @return array<string, mixed>
 */
function convertedMcqOutput(): array
{
    return [
        'prompt' => 'Which middleware runs before route handlers in Laravel?',
        'options' => ['Global middleware', 'Blade compiler', 'Mail transport', 'Queue worker'],
        'correct_answer' => ['Global middleware'],
    ];
}
