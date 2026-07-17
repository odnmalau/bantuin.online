<?php

namespace App\Http\Requests\Admin;

class GenerateCampaignQuestionRequest extends AbstractGenerateQuestionsRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'question_count' => 1,
        ]);
    }

    protected function defaultDifficulty(): string
    {
        return 'mixed';
    }
}
