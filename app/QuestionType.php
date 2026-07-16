<?php

namespace App;

enum QuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short text',
            self::LongText => 'Long text',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ShortText => 'Focused response for a concise explanation or decision.',
            self::LongText => 'Detailed response for case analysis, reasoning, or a work sample.',
        };
    }

    public function maxCharacters(): int
    {
        return match ($this) {
            self::ShortText => 1000,
            self::LongText => 10000,
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
     * @return array<int, array{value: string, label: string, description: string, max_characters: int}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $questionType): array => [
                'value' => $questionType->value,
                'label' => $questionType->label(),
                'description' => $questionType->description(),
                'max_characters' => $questionType->maxCharacters(),
            ],
            self::cases(),
        );
    }
}
