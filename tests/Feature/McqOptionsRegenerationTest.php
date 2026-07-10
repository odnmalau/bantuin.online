<?php

use App\Ai\Agents\McqOptionsRegeneratorAgent;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\User;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\QwenMcqOptionsRegenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('ai.providers.qwen.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
    config()->set('assessment.qwen.model', 'qwen3.7-plus');
});

test('admin can regenerate mcq options for draft campaign question', function () {
    McqOptionsRegeneratorAgent::fake([
        [
            'options' => ['database', 'sync', 'redis', 'sqs'],
            'correct_answer' => ['redis'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'role_title' => 'Backend Engineer',
        'required_skills' => ['Laravel'],
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Draft,
            'prompt' => 'Which queue driver should HirePilot use in production?',
            'options' => ['old-a', 'old-b'],
            'correct_answer' => ['old-a'],
        ]);

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $question->refresh();

    expect($question->options)->toBe(['database', 'sync', 'redis', 'sqs'])
        ->and($question->correct_answer)->toBe(['redis'])
        ->and($question->prompt)->toBe('Which queue driver should HirePilot use in production?');

    McqOptionsRegeneratorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Backend Engineer')
        && str_contains($prompt->prompt, 'Which queue driver should HirePilot use in production?'));
});

test('regenerate mcq options is rejected for approved campaign questions', function () {
    McqOptionsRegeneratorAgent::fake([
        [
            'options' => ['a', 'b'],
            'correct_answer' => ['a'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Approved,
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question]))
        ->assertSessionHasErrors('regeneration');

    expect($question->refresh()->options)->toBe(['A', 'B', 'C', 'D']);
});

test('regenerate mcq options is rejected for non multiple choice campaign questions', function () {
    McqOptionsRegeneratorAgent::fake([
        [
            'options' => ['a', 'b'],
            'correct_answer' => ['a'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->create([
            'type' => QuestionType::LongText,
            'status' => QuestionStatus::Draft,
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question]))
        ->assertSessionHasErrors('regeneration');
});

test('invalid regenerated mcq output is rejected without updating question', function () {
    McqOptionsRegeneratorAgent::fake([
        [
            'options' => ['a', 'b'],
            'correct_answer' => ['missing'],
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Draft,
            'options' => ['keep-a', 'keep-b', 'keep-c', 'keep-d'],
            'correct_answer' => ['keep-a'],
        ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question]))
        ->assertSessionHasErrors('regeneration');

    expect($question->refresh())
        ->options->toBe(['keep-a', 'keep-b', 'keep-c', 'keep-d'])
        ->correct_answer->toBe(['keep-a']);
});

test('candidate cannot regenerate mcq options', function () {
    McqOptionsRegeneratorAgent::fake([
        [
            'options' => ['a', 'b'],
            'correct_answer' => ['a'],
        ],
    ]);

    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create(['status' => QuestionStatus::Draft]);

    $this->actingAs($candidate)
        ->post(route('admin.campaigns.questions.regenerate-mcq-options', [$campaign, $question]))
        ->assertForbidden();
});

test('qwen mcq regenerator uses json object mode through the qwen provider', function () {
    Http::fake([
        'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'options' => ['database', 'sync', 'redis'],
                            'correct_answer' => ['database'],
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $campaign = Campaign::factory()->create([
        'role_title' => 'Backend Engineer',
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Draft,
            'prompt' => 'Pick the default queue driver.',
        ]);

    app(QwenMcqOptionsRegenerator::class)->regenerateForCampaignQuestion($question, $campaign);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
        && data_get($request->data(), 'response_format.type') === 'json_object'
        && str_contains(data_get($request->data(), 'messages.1.content'), 'Pick the default queue driver.'));
});
