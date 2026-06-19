<?php

namespace App;

enum QuestionStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Archived => 'Archived',
        };
    }
}
