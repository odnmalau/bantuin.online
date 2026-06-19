<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\TextQuestionToMcqConversionResult;
use Illuminate\Validation\ValidationException;

trait ValidatesDraftQuestionAiMutations
{
    protected function ensureDraftMcqRegeneration(QuestionType $type, QuestionStatus $status): void
    {
        if ($type !== QuestionType::MultipleChoice) {
            throw ValidationException::withMessages([
                'regeneration' => __('Only multiple choice questions support option regeneration.'),
            ]);
        }

        $this->ensureDraftQuestionStatus(
            $status,
            'regeneration',
            __('Only draft questions can regenerate MCQ options.'),
        );
    }

    protected function ensureDraftMcqConversion(QuestionType $type, QuestionStatus $status): void
    {
        if (! $type->canConvertToMcq()) {
            throw ValidationException::withMessages([
                'conversion' => __('Only short text and long text questions can be converted to multiple choice.'),
            ]);
        }

        $this->ensureDraftQuestionStatus(
            $status,
            'conversion',
            __('Only draft questions can be converted to multiple choice.'),
        );
    }

    protected function ensureDraftQuestionStatus(QuestionStatus $status, string $field, string $message): void
    {
        if ($status !== QuestionStatus::Draft) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributesAfterMcqConversion(TextQuestionToMcqConversionResult $result): array
    {
        return [
            'type' => QuestionType::MultipleChoice,
            'grading_mode' => QuestionGradingMode::Deterministic,
            'prompt' => $result->prompt,
            'options' => $result->options,
            'correct_answer' => $result->correctAnswer,
            'expected_rubric' => null,
            'ai_generated' => true,
        ];
    }
}
