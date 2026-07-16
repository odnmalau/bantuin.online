<?php

namespace App\Services\Ai\Concerns;

use App\QuestionType;
use App\Services\Ai\AssessmentGenerationException;
use Illuminate\Support\Arr;

trait NormalizesGeneratedQuestions
{
    /**
     * @return array<string, mixed>
     */
    protected function normalizeGeneratedQuestion(mixed $question, int $index): array
    {
        if (! is_array($question)) {
            throw AssessmentGenerationException::invalidOutput('each question must be an object.');
        }

        $type = QuestionType::tryFrom($this->requiredGeneratedString($question, 'type'));

        if ($type === null) {
            throw AssessmentGenerationException::invalidOutput('question type is not supported.');
        }

        $expectedRubric = $this->nullableGeneratedString($question, 'expected_rubric');

        if (blank($expectedRubric)) {
            throw AssessmentGenerationException::invalidOutput('open-ended questions require expected_rubric.');
        }

        return [
            'type' => $type,
            'prompt' => $this->requiredGeneratedString($question, 'prompt'),
            'expected_rubric' => $expectedRubric,
            'points' => $this->generatedInteger($question, 'points', 10, 1, 100),
            'difficulty' => $this->generatedEnumString($question, 'difficulty', ['easy', 'medium', 'hard'], 'medium'),
            'sort_order' => $this->generatedInteger($question, 'sort_order', ($index + 1) * 10, 0, 100000),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function requiredGeneratedString(array $data, string $key): string
    {
        $value = Arr::get($data, $key);

        if (! is_string($value) || trim($value) === '') {
            throw AssessmentGenerationException::invalidOutput("{$key} must be a non-empty string.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function nullableGeneratedString(array $data, string $key): ?string
    {
        $value = Arr::get($data, $key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw AssessmentGenerationException::invalidOutput("{$key} must be a string.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function nullableGeneratedInteger(array $data, string $key, int $min, int $max): ?int
    {
        $value = Arr::get($data, $key);

        if ($value === null || $value === '') {
            return null;
        }

        return $this->generatedInteger($data, $key, null, $min, $max);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generatedInteger(array $data, string $key, ?int $default, int $min, int $max): int
    {
        $value = Arr::get($data, $key, $default);

        if (! is_int($value)) {
            throw AssessmentGenerationException::invalidOutput("{$key} must be an integer.");
        }

        if ($value < $min || $value > $max) {
            throw AssessmentGenerationException::invalidOutput("{$key} must be between {$min} and {$max}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $allowed
     */
    protected function generatedEnumString(array $data, string $key, array $allowed, string $default): string
    {
        $value = Arr::get($data, $key, $default);

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw AssessmentGenerationException::invalidOutput("{$key} is not supported.");
        }

        return $value;
    }
}
