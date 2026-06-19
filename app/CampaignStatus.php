<?php

namespace App;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case QuestionReview = 'question_review';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::QuestionReview => 'Question Review',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
