<?php

namespace App\Http\Requests\Admin;

use App\Models\QuestionBank;

class GenerateQuestionBankQuestionsRequest extends AbstractGenerateQuestionsRequest
{
    protected function defaultLanguage(): string
    {
        return 'English';
    }

    protected function defaultDifficulty(): string
    {
        /** @var QuestionBank $questionBank */
        $questionBank = $this->route('questionBank');

        return $questionBank->difficulty ?? 'mixed';
    }
}
