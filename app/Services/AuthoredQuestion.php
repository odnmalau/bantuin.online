<?php

namespace App\Services;

use App\QuestionType;

readonly class AuthoredQuestion
{
    public function __construct(
        public QuestionType $type,
        public string $prompt,
        public ?string $expectedRubric,
        public int $points,
        public string $difficulty,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromFormInput(array $input): self
    {
        $errors = [];
        $type = self::questionType($input, $errors);
        $prompt = self::requiredString($input, 'prompt', 'prompt', $errors);
        $expectedRubric = self::nullableString($input['expected_rubric'] ?? null);
        $points = self::integer($input, 'points', 'points', 1, 100, $errors);
        $difficulty = self::difficulty($input, $errors);

        if ($type !== null && blank($expectedRubric)) {
            $errors['expected_rubric'][] = 'A rubric is required for open-ended questions.';
        }

        if ($errors !== []) {
            throw AuthoredQuestionValidationException::withErrors($errors);
        }

        return new self(
            type: $type,
            prompt: $prompt,
            expectedRubric: $expectedRubric,
            points: $points,
            difficulty: $difficulty,
        );
    }

    /**
     * @return array{
     *     type: QuestionType,
     *     prompt: string,
     *     expected_rubric: string|null,
     *     points: int,
     *     difficulty: string
     * }
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type,
            'prompt' => $this->prompt,
            'expected_rubric' => $this->expectedRubric,
            'points' => $this->points,
            'difficulty' => $this->difficulty,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<int, string>>  $errors
     */
    private static function questionType(array $input, array &$errors): ?QuestionType
    {
        $type = QuestionType::tryFrom((string) ($input['type'] ?? ''));

        if ($type === null) {
            $errors['type'][] = 'The selected question type is not supported.';

            return null;
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<int, string>>  $errors
     */
    private static function requiredString(array $input, string $key, string $field, array &$errors): string
    {
        $value = self::nullableString($input[$key] ?? null);

        if ($value === null) {
            $errors[$field][] = 'This field is required.';

            return '';
        }

        return $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<int, string>>  $errors
     */
    private static function integer(array $input, string $key, string $field, int $min, int $max, array &$errors): int
    {
        $value = $input[$key] ?? null;

        if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
            $errors[$field][] = 'This field must be an integer.';

            return 0;
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            $errors[$field][] = "This field must be between {$min} and {$max}.";
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<int, string>>  $errors
     */
    private static function difficulty(array $input, array &$errors): string
    {
        $difficulty = (string) ($input['difficulty'] ?? '');

        if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors['difficulty'][] = 'The selected difficulty is not supported.';
        }

        return $difficulty;
    }
}
