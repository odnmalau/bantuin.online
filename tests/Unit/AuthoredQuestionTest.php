<?php

use App\QuestionGradingMode;
use App\QuestionType;
use App\Services\AuthoredQuestion;
use App\Services\AuthoredQuestionValidationException;

test('it normalizes multiple choice form input into attributes', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::MultipleChoice->value,
        'prompt' => ' Which queue command retries failed jobs? ',
        'options_text' => " queue:work \n queue:retry \n\n queue:clear ",
        'correct_answer_text' => 'queue:retry',
        'points' => '10',
        'difficulty' => 'medium',
        'skill_tags_text' => " Queues \n Reliability ",
    ]);

    expect($question->toAttributes())->toMatchArray([
        'type' => QuestionType::MultipleChoice,
        'grading_mode' => QuestionGradingMode::Deterministic,
        'prompt' => 'Which queue command retries failed jobs?',
        'options' => ['queue:work', 'queue:retry', 'queue:clear'],
        'correct_answer' => ['queue:retry'],
        'expected_rubric' => null,
        'points' => 10,
        'difficulty' => 'medium',
        'skill_tags' => ['Queues', 'Reliability'],
    ]);
});

test('it forces yes no options', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::YesNo->value,
        'prompt' => 'Does Laravel support queues?',
        'options_text' => "Absolutely\nNever",
        'correct_answer_text' => 'Yes',
        'points' => 5,
        'difficulty' => 'easy',
    ]);

    expect($question->options)->toBe(['Yes', 'No']);
});

test('it allows manual text questions with a rubric', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::LongText->value,
        'grading_mode' => QuestionGradingMode::Manual->value,
        'prompt' => 'Explain queue retries.',
        'expected_rubric' => 'Mentions failed jobs, retry commands, and monitoring.',
        'points' => 20,
        'difficulty' => 'hard',
    ]);

    expect($question->gradingMode)->toBe(QuestionGradingMode::Manual)
        ->and($question->expectedRubric)->toContain('failed jobs');
});

test('it rejects incompatible grading modes', function () {
    try {
        AuthoredQuestion::fromFormInput([
            'type' => QuestionType::MultipleChoice->value,
            'grading_mode' => QuestionGradingMode::Manual->value,
            'prompt' => 'Pick one.',
            'options_text' => "A\nB",
            'correct_answer_text' => 'A',
            'points' => 10,
            'difficulty' => 'medium',
        ]);
    } catch (AuthoredQuestionValidationException $exception) {
        expect($exception->errors())->toHaveKey('grading_mode');

        return;
    }

    $this->fail('Expected authored question validation to fail.');
});

test('it requires exactly one correct answer for multiple choice questions', function () {
    try {
        AuthoredQuestion::fromFormInput([
            'type' => QuestionType::MultipleChoice->value,
            'prompt' => 'Pick one.',
            'options_text' => "A\nB",
            'correct_answer_text' => "A\nB",
            'points' => 10,
            'difficulty' => 'medium',
        ]);
    } catch (AuthoredQuestionValidationException $exception) {
        expect($exception->errors())->toHaveKey('correct_answer_text');

        return;
    }

    $this->fail('Expected authored question validation to fail.');
});

test('it allows multiple accepted answers for fill in the blank questions', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::FillBlank->value,
        'prompt' => 'Laravel stores queued jobs in the ____ table.',
        'correct_answer_text' => "jobs\nqueue_jobs",
        'points' => 10,
        'difficulty' => 'medium',
    ]);

    expect($question->correctAnswer)->toBe(['jobs', 'queue_jobs']);
});

test('it rejects matching pairs from human authored form input', function () {
    try {
        AuthoredQuestion::fromFormInput([
            'type' => QuestionType::MatchingPairs->value,
            'prompt' => 'Match terms.',
            'correct_answer_text' => 'queue => worker',
            'points' => 10,
            'difficulty' => 'medium',
        ]);
    } catch (AuthoredQuestionValidationException $exception) {
        expect($exception->errors())->toHaveKey('type');

        return;
    }

    $this->fail('Expected authored question validation to fail.');
});

test('it enforces authored list item length limits', function () {
    try {
        AuthoredQuestion::fromFormInput([
            'type' => QuestionType::LongText->value,
            'prompt' => 'Explain queue retries.',
            'expected_rubric' => 'Mentions failed jobs.',
            'points' => 10,
            'difficulty' => 'medium',
            'skill_tags_text' => str_repeat('a', 101),
        ]);
    } catch (AuthoredQuestionValidationException $exception) {
        expect($exception->errors())->toHaveKey('skill_tags_text');

        return;
    }

    $this->fail('Expected authored question validation to fail.');
});
