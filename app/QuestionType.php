<?php

namespace App;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case FillBlank = 'fill_blank';
    case MatchingPairs = 'matching_pairs';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple choice',
            self::YesNo => 'Yes or no',
            self::ShortText => 'Short text',
            self::LongText => 'Long text',
            self::FillBlank => 'Fill in the blank',
            self::MatchingPairs => 'Matching pairs',
        };
    }

    public function usesDeterministicGrading(): bool
    {
        return match ($this) {
            self::MultipleChoice, self::YesNo, self::FillBlank, self::MatchingPairs => true,
            self::ShortText, self::LongText => false,
        };
    }

    public function canConvertToMcq(): bool
    {
        return match ($this) {
            self::ShortText, self::LongText => true,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $questionType): string => $questionType->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, deterministic: bool}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $questionType): array => [
                'value' => $questionType->value,
                'label' => $questionType->label(),
                'deterministic' => $questionType->usesDeterministicGrading(),
            ],
            self::cases(),
        );
    }
}
