<?php

namespace App\Services;

use App\QuestionGradingMode;
use App\QuestionType;

readonly class AuthoredQuestion
{
    /**
     * @param  array<int, string>|null  $options
     * @param  array<int, string>|null  $correctAnswer
     * @param  array<int, string>|null  $skillTags
     */
    public function __construct(
        public QuestionType $type,
        public QuestionGradingMode $gradingMode,
        public string $prompt,
        public ?array $options,
        public ?array $correctAnswer,
        public ?string $expectedRubric,
        public int $points,
        public string $difficulty,
        public ?array $skillTags,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromFormInput(array $input): self
    {
        $errors = [];
        $type = self::questionType($input, $errors);
        $gradingMode = self::gradingMode($input, $type, $errors);
        $prompt = self::requiredString($input, 'prompt', 'prompt', $errors);
        $options = self::stringList($input['options_text'] ?? null, 'options_text', 500, $errors);
        $correctAnswer = self::stringList($input['correct_answer_text'] ?? null, 'correct_answer_text', 500, $errors);
        $expectedRubric = self::nullableString($input['expected_rubric'] ?? null);
        $points = self::integer($input, 'points', 'points', 1, 1000, $errors);
        $difficulty = self::difficulty($input, $errors);
        $skillTags = self::stringList($input['skill_tags_text'] ?? null, 'skill_tags_text', 100, $errors);

        if ($type !== null && $gradingMode !== null) {
            self::validateCompatibility($type, $gradingMode, $errors);
        }

        if ($type !== null) {
            [$options, $correctAnswer] = self::validateAnswerShape($type, $options, $correctAnswer, $errors);
        }

        if ($type !== null && ! $type->usesDeterministicGrading() && blank($expectedRubric)) {
            $errors['expected_rubric'][] = 'A rubric is required for AI or manually graded text questions.';
        }

        if ($errors !== []) {
            throw AuthoredQuestionValidationException::withErrors($errors);
        }

        return new self(
            type: $type,
            gradingMode: $gradingMode,
            prompt: $prompt,
            options: $options,
            correctAnswer: $correctAnswer,
            expectedRubric: $expectedRubric,
            points: $points,
            difficulty: $difficulty,
            skillTags: $skillTags,
        );
    }

    /**
     * @return array{
     *     type: QuestionType,
     *     grading_mode: QuestionGradingMode,
     *     prompt: string,
     *     options: array<int, string>|null,
     *     correct_answer: array<int, string>|null,
     *     expected_rubric: string|null,
     *     points: int,
     *     difficulty: string,
     *     skill_tags: array<int, string>|null
     * }
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type,
            'grading_mode' => $this->gradingMode,
            'prompt' => $this->prompt,
            'options' => $this->options,
            'correct_answer' => $this->correctAnswer,
            'expected_rubric' => $this->expectedRubric,
            'points' => $this->points,
            'difficulty' => $this->difficulty,
            'skill_tags' => $this->skillTags,
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

        if ($type === QuestionType::MatchingPairs) {
            $errors['type'][] = 'Matching pairs cannot be authored from this form yet.';
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array<int, string>>  $errors
     */
    private static function gradingMode(array $input, ?QuestionType $type, array &$errors): ?QuestionGradingMode
    {
        $value = $input['grading_mode'] ?? null;

        if ($value === null || $value === '') {
            return $type === null ? null : QuestionGradingMode::forQuestionType($type);
        }

        $gradingMode = QuestionGradingMode::tryFrom((string) $value);

        if ($gradingMode === null) {
            $errors['grading_mode'][] = 'The selected grading mode is not supported.';
        }

        return $gradingMode;
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
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, string>|null
     */
    private static function stringList(mixed $value, string $field, int $maxLength, array &$errors): ?array
    {
        if ($value === null) {
            return null;
        }

        $items = is_array($value)
            ? $value
            : preg_split('/[\r\n]+/', (string) $value);

        $items = array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $items ?: []),
            fn (string $item): bool => $item !== '',
        ));

        foreach ($items as $item) {
            if (mb_strlen($item) > $maxLength) {
                $errors[$field][] = "Each item must be {$maxLength} characters or fewer.";

                break;
            }
        }

        return $items === [] ? null : $items;
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

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function validateCompatibility(
        QuestionType $type,
        QuestionGradingMode $gradingMode,
        array &$errors,
    ): void {
        if ($type->usesDeterministicGrading() && $gradingMode !== QuestionGradingMode::Deterministic) {
            $errors['grading_mode'][] = 'Auto-graded question types must use deterministic grading.';
        }

        if (! $type->usesDeterministicGrading()
            && ! in_array($gradingMode, [QuestionGradingMode::Ai, QuestionGradingMode::Manual], true)) {
            $errors['grading_mode'][] = 'Text questions must use AI or manual grading.';
        }
    }

    /**
     * @param  array<int, string>|null  $options
     * @param  array<int, string>|null  $correctAnswer
     * @param  array<string, array<int, string>>  $errors
     * @return array{0: array<int, string>|null, 1: array<int, string>|null}
     */
    private static function validateAnswerShape(
        QuestionType $type,
        ?array $options,
        ?array $correctAnswer,
        array &$errors,
    ): array {
        if ($type === QuestionType::YesNo) {
            $options = ['Yes', 'No'];
        }

        if ($type === QuestionType::MultipleChoice) {
            if (count($options ?? []) < 2) {
                $errors['options_text'][] = 'Multiple choice questions need at least two answer options.';
            }

            if (count($correctAnswer ?? []) !== 1) {
                $errors['correct_answer_text'][] = 'Multiple choice questions require exactly one correct answer.';
            } elseif (! in_array($correctAnswer[0], $options ?? [], true)) {
                $errors['correct_answer_text'][] = 'The correct answer must match one of the answer options.';
            }
        }

        if ($type === QuestionType::YesNo) {
            if (count($correctAnswer ?? []) !== 1) {
                $errors['correct_answer_text'][] = 'Yes or no questions require exactly one correct answer.';
            } elseif (! in_array($correctAnswer[0], $options, true)) {
                $errors['correct_answer_text'][] = 'The correct answer must be Yes or No.';
            }
        }

        if ($type === QuestionType::FillBlank && count($correctAnswer ?? []) < 1) {
            $errors['correct_answer_text'][] = 'Fill in the blank questions require at least one accepted answer.';
        }

        return [$options, $correctAnswer];
    }
}
