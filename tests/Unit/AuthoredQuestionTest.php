<?php

use App\QuestionType;
use App\Services\AuthoredQuestion;
use App\Services\AuthoredQuestionValidationException;

test('it normalizes open ended form input into attributes', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::LongText->value,
        'prompt' => ' Explain how you would handle a failed deployment. ',
        'expected_rubric' => 'Mentions rollback, communication, investigation, and prevention.',
        'points' => '10',
        'difficulty' => 'medium',
    ]);

    expect($question->toAttributes())->toMatchArray([
        'type' => QuestionType::LongText,
        'prompt' => 'Explain how you would handle a failed deployment.',
        'expected_rubric' => 'Mentions rollback, communication, investigation, and prevention.',
        'points' => 10,
        'difficulty' => 'medium',
    ]);
});

test('it ignores removed grading mode input', function () {
    $question = AuthoredQuestion::fromFormInput([
        'type' => QuestionType::ShortText->value,
        'grading_mode' => 'manual',
        'prompt' => 'Describe your first response to a production incident.',
        'expected_rubric' => 'Prioritizes safety, communication, and diagnosis.',
        'points' => 20,
        'difficulty' => 'hard',
    ]);

    expect($question->toAttributes())->not->toHaveKey('grading_mode');
});

test('it requires a rubric for every open ended question', function () {
    expect(fn () => AuthoredQuestion::fromFormInput([
        'type' => QuestionType::LongText->value,
        'prompt' => 'Explain queue retries.',
        'points' => 10,
        'difficulty' => 'medium',
    ]))->toThrow(AuthoredQuestionValidationException::class);
});

test('it rejects removed objective question types', function () {
    try {
        AuthoredQuestion::fromFormInput([
            'type' => 'multiple_choice',
            'prompt' => 'Pick one.',
            'expected_rubric' => 'Not applicable.',
            'points' => 10,
            'difficulty' => 'medium',
        ]);
    } catch (AuthoredQuestionValidationException $exception) {
        expect($exception->errors())->toHaveKey('type');

        return;
    }

    $this->fail('Expected authored question validation to fail.');
});

test('it rejects question points above the supported maximum', function () {
    expect(fn () => AuthoredQuestion::fromFormInput([
        'type' => QuestionType::LongText->value,
        'prompt' => 'Explain queue retries.',
        'expected_rubric' => 'Mentions failed jobs.',
        'points' => 101,
        'difficulty' => 'medium',
    ]))->toThrow(AuthoredQuestionValidationException::class);
});
