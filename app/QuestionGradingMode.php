<?php

namespace App;

enum QuestionGradingMode: string
{
    case Deterministic = 'deterministic';
    case Ai = 'ai';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Deterministic => 'Deterministic',
            self::Ai => 'AI',
            self::Manual => 'Manual',
        };
    }

    public static function forQuestionType(QuestionType $type): self
    {
        return $type->usesDeterministicGrading()
            ? self::Deterministic
            : self::Ai;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            self::cases(),
        );
    }
}
