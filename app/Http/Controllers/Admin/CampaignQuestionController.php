<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignQuestionRequest;
use App\Http\Requests\Admin\UpdateCampaignQuestionRequest;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Services\Ai\QwenMcqOptionsRegenerator;
use App\Services\Ai\QwenTextQuestionToMcqConverter;
use App\Services\CampaignLifecycleService;
use App\Services\DraftQuestionMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CampaignQuestionController extends Controller
{
    use HandlesAssessmentGenerationFailures;

    /**
     * Store a campaign question snapshot.
     */
    public function store(
        StoreCampaignQuestionRequest $request,
        Campaign $campaign,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request): Campaign {
                $lockedCampaign->questions()->create($request->questionAttributes());

                return $lockedCampaign;
            },
        );

        $this->flashSuccessToast(__('Question added to campaign.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Update a campaign question.
     */
    public function update(
        UpdateCampaignQuestionRequest $request,
        Campaign $campaign,
        CampaignQuestion $question,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $question): Campaign {
                $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                $this->ensureQuestionBelongsToCampaign($lockedCampaign, $lockedQuestion);
                $lockedQuestion->update($request->questionAttributes());

                return $lockedCampaign;
            },
        );

        $this->flashSuccessToast(__('Question updated.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Regenerate multiple choice options for a draft campaign question.
     */
    public function regenerateMcqOptions(
        Campaign $campaign,
        CampaignQuestion $question,
        QwenMcqOptionsRegenerator $regenerator,
        DraftQuestionMutation $mutation,
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $this->runAssessmentGeneration(
            'regeneration',
            fn () => $mutation->regenerateMcqOptions(
                $question,
                fn () => $regenerator->regenerateForCampaignQuestion($question, $campaign),
            ),
        );

        $this->flashSuccessToast(__('Multiple choice options regenerated.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Convert a draft text question into a multiple choice question.
     */
    public function convertToMcq(
        Campaign $campaign,
        CampaignQuestion $question,
        QwenTextQuestionToMcqConverter $converter,
        DraftQuestionMutation $mutation,
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $this->runAssessmentGeneration(
            'conversion',
            fn () => $mutation->convertToMcq(
                $question,
                fn () => $converter->convertCampaignQuestion($question, $campaign),
            ),
        );

        $this->flashSuccessToast(__('Question converted to multiple choice.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Approve a generated draft campaign question.
     */
    public function approve(Campaign $campaign, CampaignQuestion $question, DraftQuestionMutation $mutation): RedirectResponse
    {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $mutation->approveCampaignQuestion($question);

        $this->flashSuccessToast(__('Question approved.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Approve all generated draft questions in the campaign.
     */
    public function approveAll(Campaign $campaign, DraftQuestionMutation $mutation): RedirectResponse
    {
        $approvedQuestions = $mutation->approveAllCampaignDrafts($campaign);

        $this->flashSuccessToast(trans_choice(
            'Approved :count draft question.|Approved :count draft questions.',
            $approvedQuestions,
            ['count' => $approvedQuestions],
        ));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Delete a campaign question snapshot.
     */
    public function destroy(
        Campaign $campaign,
        CampaignQuestion $question,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($question): Campaign {
                $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                $this->ensureQuestionBelongsToCampaign($lockedCampaign, $lockedQuestion);
                $lockedQuestion->delete();

                return $lockedCampaign;
            },
        );

        $this->flashSuccessToast(__('Question removed from campaign.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Ensure a question snapshot belongs to the routed campaign.
     */
    private function ensureQuestionBelongsToCampaign(Campaign $campaign, CampaignQuestion $question): void
    {
        if ($question->campaign_id !== $campaign->id) {
            throw ValidationException::withMessages([
                'question' => __('The selected question does not belong to this campaign.'),
            ]);
        }
    }
}
