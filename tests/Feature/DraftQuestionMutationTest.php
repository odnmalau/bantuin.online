<?php

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\McqOptionsRegenerationResult;
use App\Services\Ai\TextQuestionToMcqConversionResult;
use App\Services\DraftQuestionMutation;
use Illuminate\Validation\ValidationException;

test('draft question mutation regenerates multiple choice options', function () {
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Draft,
            'options' => ['old-a', 'old-b'],
            'correct_answer' => ['old-a'],
        ]);
    $called = false;

    app(DraftQuestionMutation::class)->regenerateMcqOptions(
        $question,
        function () use (&$called): McqOptionsRegenerationResult {
            $called = true;

            return new McqOptionsRegenerationResult(
                options: ['database', 'sync', 'redis', 'sqs'],
                correctAnswer: ['redis'],
            );
        },
    );

    expect($called)->toBeTrue()
        ->and($question->refresh())
        ->options->toBe(['database', 'sync', 'redis', 'sqs'])
        ->correct_answer->toBe(['redis']);
});

test('draft question mutation does not regenerate non draft questions', function () {
    $question = CampaignQuestion::factory()
        ->multipleChoice()
        ->create([
            'status' => QuestionStatus::Approved,
            'options' => ['keep-a', 'keep-b'],
            'correct_answer' => ['keep-a'],
        ]);
    $called = false;

    try {
        app(DraftQuestionMutation::class)->regenerateMcqOptions(
            $question,
            function () use (&$called): McqOptionsRegenerationResult {
                $called = true;

                return new McqOptionsRegenerationResult(['new-a', 'new-b'], ['new-a']);
            },
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('regeneration')
            ->and($called)->toBeFalse()
            ->and($question->refresh())
            ->options->toBe(['keep-a', 'keep-b'])
            ->correct_answer->toBe(['keep-a']);

        return;
    }

    throw new RuntimeException('Expected draft regeneration validation to fail.');
});

test('draft question mutation converts text questions to multiple choice', function () {
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'type' => QuestionType::LongText,
            'grading_mode' => QuestionGradingMode::Ai,
            'prompt' => 'Explain queue workers.',
            'expected_rubric' => 'Mentions retries and failed jobs.',
            'points' => 10,
            'difficulty' => 'medium',
            'skill_tags' => ['Laravel'],
            'ai_generated' => false,
            'status' => QuestionStatus::Draft,
            'sort_order' => 10,
        ]);

    app(DraftQuestionMutation::class)->convertToMcq(
        $question,
        fn (): TextQuestionToMcqConversionResult => new TextQuestionToMcqConversionResult(
            prompt: 'Which queue feature handles transient failures?',
            options: ['Retries', 'Blade slots', 'View composers', 'Asset bundling'],
            correctAnswer: ['Retries'],
        ),
    );

    expect($question->refresh())
        ->type->toBe(QuestionType::MultipleChoice)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->prompt->toBe('Which queue feature handles transient failures?')
        ->options->toBe(['Retries', 'Blade slots', 'View composers', 'Asset bundling'])
        ->correct_answer->toBe(['Retries'])
        ->expected_rubric->toBeNull()
        ->ai_generated->toBeTrue();
});

test('draft question mutation approves campaign drafts', function () {
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $draft = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Draft]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $mutation = app(DraftQuestionMutation::class);

    $mutation->approveCampaignQuestion($draft);
    $approvedCount = $mutation->approveAllCampaignDrafts($campaign);

    expect($draft->refresh()->status)->toBe(QuestionStatus::Approved)
        ->and($approvedCount)->toBe(0)
        ->and($campaign->questions()->where('status', QuestionStatus::Draft->value)->exists())->toBeFalse();
});
