<?php

namespace App\Http\Controllers\Admin\Concerns;

trait ProvidesQuestionDifficultyOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function difficultyOptions(): array
    {
        return [
            ['value' => 'easy', 'label' => 'Easy'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'hard', 'label' => 'Hard'],
        ];
    }
}
