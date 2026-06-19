<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateQuestionBankQuestionsRequest;
use App\Models\QuestionBank;
use App\Services\Ai\QwenQuestionBankGenerator;
use Illuminate\Http\RedirectResponse;

class QuestionBankQuestionGenerationController extends Controller
{
    use HandlesAssessmentGenerationFailures;

    /**
     * Generate additional draft questions for a question library.
     */
    public function store(
        GenerateQuestionBankQuestionsRequest $request,
        QuestionBank $questionBank,
        QwenQuestionBankGenerator $generator,
    ): RedirectResponse {
        return $this->generateDraftsAndRedirect(
            'generation',
            fn (): int => $generator->generate($questionBank, $request->validated()),
            'admin.question-banks.show',
            $questionBank,
        );
    }
}
