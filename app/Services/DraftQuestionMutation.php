<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\QuestionStatus;
use Illuminate\Validation\ValidationException;

class DraftQuestionMutation
{
    public function __construct(
        private CampaignLifecycleService $lifecycle,
        private CampaignSectionDistribution $distribution,
    ) {}

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

    public function discardAllCampaignDrafts(Campaign $campaign): int
    {
        return $this->lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign): int {
                $affectedSectionIds = $lockedCampaign->questions()
                    ->where('status', QuestionStatus::Draft->value)
                    ->distinct()
                    ->pluck('campaign_section_id');

                $discardedQuestions = $lockedCampaign->questions()
                    ->where('status', QuestionStatus::Draft->value)
                    ->delete();

                if ($affectedSectionIds->isNotEmpty()) {
                    $lockedCampaign->sections()
                        ->whereKey($affectedSectionIds)
                        ->doesntHave('questions')
                        ->delete();
                }

                $this->distribution->normalize($lockedCampaign);

                return $discardedQuestions;
            },
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
}
