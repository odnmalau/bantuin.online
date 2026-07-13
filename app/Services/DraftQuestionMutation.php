<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\McqOptionsRegenerationResult;
use App\Services\Ai\TextQuestionToMcqConversionResult;
use App\TeamStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DraftQuestionMutation
{
    public function __construct(private CampaignLifecycleService $lifecycle) {}

    /**
     * @param  callable(): McqOptionsRegenerationResult  $regenerate
     */
    public function regenerateMcqOptions(CampaignQuestion $question, callable $regenerate): void
    {
        $this->ensureDraftMcqRegeneration($question);
        $expectedTeamId = $question->campaign()->value('team_id');
        $result = $regenerate();

        $this->updateAfterAi(
            $question,
            $expectedTeamId,
            [
                'options' => $result->options,
                'correct_answer' => $result->correctAnswer,
            ],
            fn (CampaignQuestion $lockedQuestion) => $this->ensureDraftMcqRegeneration($lockedQuestion),
        );
    }

    /**
     * @param  callable(): TextQuestionToMcqConversionResult  $convert
     */
    public function convertToMcq(CampaignQuestion $question, callable $convert): void
    {
        $this->ensureDraftMcqConversion($question);
        $expectedTeamId = $question->campaign()->value('team_id');
        $attributes = $this->attributesAfterMcqConversion($convert());

        $this->updateAfterAi(
            $question,
            $expectedTeamId,
            $attributes,
            fn (CampaignQuestion $lockedQuestion) => $this->ensureDraftMcqConversion($lockedQuestion),
        );
    }

    public function approveCampaignQuestion(CampaignQuestion $question): void
    {
        $campaign = Campaign::query()->findOrFail($question->campaign_id);

        $this->lifecycle->withEditableDefinition($campaign, function (Campaign $lockedCampaign) use ($question): void {
            $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();

            if ($lockedQuestion->campaign_id !== $lockedCampaign->id) {
                throw ValidationException::withMessages([
                    'question' => __('The selected question does not belong to this campaign.'),
                ]);
            }

            $this->ensureDraftQuestionStatus(
                $lockedQuestion->status,
                'question',
                __('Only draft questions can be approved.'),
            );

            $lockedQuestion->update([
                'status' => QuestionStatus::Approved,
            ]);
        });
    }

    public function approveAllCampaignDrafts(Campaign $campaign): int
    {
        return $this->lifecycle->withEditableDefinition(
            $campaign,
            fn (Campaign $lockedCampaign): int => $lockedCampaign->questions()
                ->where('status', QuestionStatus::Draft->value)
                ->update([
                    'status' => QuestionStatus::Approved,
                ]),
        );
    }

    private function ensureDraftMcqRegeneration(CampaignQuestion $question): void
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

    private function ensureDraftMcqConversion(CampaignQuestion $question): void
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

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(CampaignQuestion): void  $validate
     */
    private function updateAfterAi(
        CampaignQuestion $question,
        int $expectedTeamId,
        array $attributes,
        callable $validate,
    ): void {
        DB::transaction(function () use ($question, $expectedTeamId, $attributes, $validate): void {
            $campaign = Campaign::query()
                ->whereKey($question->campaign_id)
                ->where('team_id', $expectedTeamId)
                ->lockForUpdate()
                ->first();

            if ($campaign === null) {
                throw ValidationException::withMessages([
                    'question' => __('The Campaign Team is no longer writable.'),
                ]);
            }

            $this->lifecycle->assertDefinitionEditable($campaign);
            $team = $campaign?->team()
                ->where('status', TeamStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($team === null) {
                throw ValidationException::withMessages([
                    'question' => __('The Campaign Team is no longer writable.'),
                ]);
            }

            $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();

            if ($lockedQuestion->campaign_id !== $campaign->id) {
                throw ValidationException::withMessages([
                    'question' => __('The selected question does not belong to this campaign.'),
                ]);
            }

            $validate($lockedQuestion);
            $lockedQuestion->update($attributes);
        });
    }
}
