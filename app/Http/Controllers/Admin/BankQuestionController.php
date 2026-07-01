<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Admin\Concerns\ProvidesQuestionDifficultyOptions;
use App\Http\Controllers\Admin\Concerns\ValidatesDraftQuestionAiMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankQuestionRequest;
use App\Http\Requests\Admin\UpdateBankQuestionRequest;
use App\Models\BankQuestion;
use App\Models\QuestionBank;
use App\QuestionGradingMode;
use App\QuestionType;
use App\Services\Ai\QwenMcqOptionsRegenerator;
use App\Services\Ai\QwenTextQuestionToMcqConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankQuestionController extends Controller
{
    use HandlesAssessmentGenerationFailures;
    use ProvidesQuestionDifficultyOptions;
    use ValidatesDraftQuestionAiMutations;

    /**
     * Show the form for creating a reusable question.
     */
    public function create(QuestionBank $questionBank): Response
    {
        return Inertia::render('admin/question-banks/questions/create', [
            'questionBank' => $this->questionBankPayload($questionBank),
            'questionTypes' => QuestionType::selectOptions(),
            'gradingModeOptions' => QuestionGradingMode::selectOptions(),
            'difficultyOptions' => $this->difficultyOptions(),
        ]);
    }

    /**
     * Store a reusable question.
     */
    public function store(StoreBankQuestionRequest $request, QuestionBank $questionBank): RedirectResponse
    {
        $questionBank->questions()->create($request->questionAttributes());

        $this->flashSuccessToast(__('Question added to library.'));

        return to_route('admin.question-banks.show', $questionBank);
    }

    /**
     * Show the form for editing a reusable question.
     */
    public function edit(QuestionBank $questionBank, BankQuestion $bankQuestion): Response
    {
        $this->ensureQuestionBelongsToBank($questionBank, $bankQuestion);

        return Inertia::render('admin/question-banks/questions/edit', [
            'questionBank' => $this->questionBankPayload($questionBank),
            'question' => [
                'id' => $bankQuestion->id,
                'type' => $bankQuestion->type->value,
                'grading_mode' => $bankQuestion->grading_mode->value,
                'prompt' => $bankQuestion->prompt,
                'options' => $bankQuestion->options ?? [],
                'correct_answer' => $bankQuestion->correct_answer ?? [],
                'expected_rubric' => $bankQuestion->expected_rubric,
                'points' => $bankQuestion->points,
                'difficulty' => $bankQuestion->difficulty,
                'skill_tags' => $bankQuestion->skill_tags ?? [],
                'ai_generated' => $bankQuestion->ai_generated,
                'status' => $bankQuestion->status->value,
                'status_label' => $bankQuestion->status->label(),
                'sort_order' => $bankQuestion->sort_order,
            ],
            'questionTypes' => QuestionType::selectOptions(),
            'gradingModeOptions' => QuestionGradingMode::selectOptions(),
            'difficultyOptions' => $this->difficultyOptions(),
        ]);
    }

    /**
     * Update a reusable question.
     */
    public function update(UpdateBankQuestionRequest $request, QuestionBank $questionBank, BankQuestion $bankQuestion): RedirectResponse
    {
        $this->ensureQuestionBelongsToBank($questionBank, $bankQuestion);

        $bankQuestion->update($request->questionAttributes());

        $this->flashSuccessToast(__('Question updated.'));

        return to_route('admin.question-banks.show', $questionBank);
    }

    /**
     * Regenerate multiple choice options for a draft library question.
     */
    public function regenerateMcqOptions(
        QuestionBank $questionBank,
        BankQuestion $bankQuestion,
        QwenMcqOptionsRegenerator $regenerator,
    ): RedirectResponse {
        $this->ensureQuestionBelongsToBank($questionBank, $bankQuestion);
        $this->ensureDraftMcqRegeneration($bankQuestion->type, $bankQuestion->status);

        $result = $this->runAssessmentGeneration(
            'regeneration',
            fn () => $regenerator->regenerateForBankQuestion($bankQuestion, $questionBank),
        );

        $bankQuestion->update([
            'options' => $result->options,
            'correct_answer' => $result->correctAnswer,
        ]);

        $this->flashSuccessToast(__('Multiple choice options regenerated.'));

        return to_route('admin.question-banks.questions.edit', [$questionBank, $bankQuestion]);
    }

    /**
     * Convert a draft text library question into multiple choice.
     */
    public function convertToMcq(
        QuestionBank $questionBank,
        BankQuestion $bankQuestion,
        QwenTextQuestionToMcqConverter $converter,
    ): RedirectResponse {
        $this->ensureQuestionBelongsToBank($questionBank, $bankQuestion);
        $this->ensureDraftMcqConversion($bankQuestion->type, $bankQuestion->status);

        $result = $this->runAssessmentGeneration(
            'conversion',
            fn () => $converter->convertBankQuestion($bankQuestion, $questionBank),
        );

        $bankQuestion->update($this->attributesAfterMcqConversion($result));

        $this->flashSuccessToast(__('Question converted to multiple choice.'));

        return to_route('admin.question-banks.questions.edit', [$questionBank, $bankQuestion]);
    }

    /**
     * Delete a reusable question.
     */
    public function destroy(QuestionBank $questionBank, BankQuestion $bankQuestion): RedirectResponse
    {
        $this->ensureQuestionBelongsToBank($questionBank, $bankQuestion);

        $bankQuestion->delete();

        $this->flashSuccessToast(__('Question removed from library.'));

        return to_route('admin.question-banks.show', $questionBank);
    }

    /**
     * @return array{id: int, title: string}
     */
    private function questionBankPayload(QuestionBank $questionBank): array
    {
        return [
            'id' => $questionBank->id,
            'title' => $questionBank->title,
        ];
    }

    private function ensureQuestionBelongsToBank(QuestionBank $questionBank, BankQuestion $bankQuestion): void
    {
        if ($bankQuestion->question_bank_id !== $questionBank->id) {
            throw ValidationException::withMessages([
                'question' => __('The selected question does not belong to this question library.'),
            ]);
        }
    }
}
