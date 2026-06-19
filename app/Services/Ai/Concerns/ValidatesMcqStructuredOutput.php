<?php

namespace App\Services\Ai\Concerns;

use App\Services\Ai\AssessmentGenerationException;
use Illuminate\Support\Arr;

trait ValidatesMcqStructuredOutput
{
    /**
     * @param  array<string, mixed>  $output
     * @return array{options: array<int, string>, correct_answer: array<int, string>}
     */
    protected function validatedMcqOutput(array $output): array
    {
        $options = $this->mcqStringList(Arr::get($output, 'options'));
        $correctAnswer = $this->mcqStringList(Arr::get($output, 'correct_answer'));

        if ($options === null || count($options) < 2) {
            throw AssessmentGenerationException::invalidOutput('multiple choice questions require at least two options.');
        }

        if ($correctAnswer === null || count($correctAnswer) !== 1) {
            throw AssessmentGenerationException::invalidOutput('correct_answer must contain exactly one value.');
        }

        if (! in_array($correctAnswer[0], $options, true)) {
            throw AssessmentGenerationException::invalidOutput('correct_answer must match one of the options.');
        }

        return [
            'options' => array_values(array_unique($options)),
            'correct_answer' => $correctAnswer,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function mcqStringList(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw AssessmentGenerationException::invalidOutput('list fields must be arrays.');
        }

        $items = array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
            fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
    }
}
