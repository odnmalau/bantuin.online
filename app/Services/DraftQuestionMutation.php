<?php

namespace App\Services;

use App\Models\BankQuestion;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\McqOptionsRegenerationResult;
use App\Services\Ai\TextQuestionToMcqConversionResult;
use Illuminate\Validation\ValidationException;

class DraftQuestionMutation
{
    /**
     * @param  callable(): McqOptionsRegenerationResult  $regenerate
     */
    public function regenerateMcqOptions(CampaignQuestion|BankQuestion $question, callable $regenerate): void
    {
        $this->ensureDraftMcqRegeneration($question);

        $result = $regenerate();

        $question->update([
            'options' => $result->options,
            'correct_answer' => $result->correctAnswer,
        ]);
    }

    /**
     * @param  callable(): TextQuestionToMcqConversionResult  $convert
     */
    public function convertToMcq(CampaignQuestion|BankQuestion $question, callable $convert): void
    {
        $this->ensureDraftMcqConversion($question);

        $question->update($this->attributesAfterMcqConversion($convert()));
    }

    public function approveCampaignQuestion(CampaignQuestion $question): void
    {
        $this->ensureDraftQuestionStatus(
            $question->status,
            'question',
            __('Only draft questions can be approved.'),
        );

        $question->update([
            'status' => QuestionStatus::Approved,
        ]);
    }

    public function approveAllCampaignDrafts(Campaign $campaign): int
    {
        return $campaign->questions()
            ->where('status', QuestionStatus::Draft->value)
            ->update([
                'status' => QuestionStatus::Approved,
            ]);
    }

    private function ensureDraftMcqRegeneration(CampaignQuestion|BankQuestion $question): void
    {
        if ($question->type !== QuestionType::MultipleChoice) {
            throw ValidationException::withMessages([
                'regeneration' => __('Only multiple choice questions support option regeneration.'),
            ]);
        }

        $this->ensureDraftQuestionStatus(
            $question->status,
            'regeneration',
            __('Only draft questions can regenerate MCQ options.'),
        );
    }

    private function ensureDraftMcqConversion(CampaignQuestion|BankQuestion $question): void
    {
        if (! $question->type->canConvertToMcq()) {
            throw ValidationException::withMessages([
                'conversion' => __('Only short text and long text questions can be converted to multiple choice.'),
            ]);
        }

        $this->ensureDraftQuestionStatus(
            $question->status,
            'conversion',
            __('Only draft questions can be converted to multiple choice.'),
        );
    }

    private function ensureDraftQuestionStatus(QuestionStatus $status, string $field, string $message): void
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
    private function attributesAfterMcqConversion(TextQuestionToMcqConversionResult $result): array
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
