<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderCampaignQuestionsRequest;
use App\Http\Requests\Admin\StoreCampaignQuestionRequest;
use App\Http\Requests\Admin\UpdateCampaignQuestionRequest;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Services\CampaignDefinitionOrder;
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
        CampaignDefinitionOrder $order,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $order): Campaign {
                $attributes = $request->questionAttributes();
                $attributes['sort_order'] = $order->nextQuestionSortOrder(
                    $lockedCampaign,
                    $attributes['campaign_section_id'],
                );
                $lockedCampaign->questions()->create($attributes);

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
        CampaignDefinitionOrder $order,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $question, $order): Campaign {
                $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                $this->ensureQuestionBelongsToCampaign($lockedCampaign, $lockedQuestion);
                $originalSection = $lockedQuestion->section;
                $attributes = $request->questionAttributes();

                if ($lockedQuestion->campaign_section_id !== $attributes['campaign_section_id']) {
                    $attributes['sort_order'] = $order->nextQuestionSortOrder(
                        $lockedCampaign,
                        $attributes['campaign_section_id'],
                    );
                }

                $lockedQuestion->update($attributes);
                $order->normalizeQuestions($originalSection);

                return $lockedCampaign;
            },
        );

        $this->flashSuccessToast(__('Question updated.'));

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
     * Permanently delete all draft questions in the campaign.
     */
    public function discardAll(Campaign $campaign, DraftQuestionMutation $mutation): RedirectResponse
    {
        $discardedQuestions = $mutation->discardAllCampaignDrafts($campaign);

        $this->flashSuccessToast(trans_choice(
            'Discarded :count draft question.|Discarded :count draft questions.',
            $discardedQuestions,
            ['count' => $discardedQuestions],
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
        CampaignDefinitionOrder $order,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($question, $order): Campaign {
                $lockedQuestion = CampaignQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                $this->ensureQuestionBelongsToCampaign($lockedCampaign, $lockedQuestion);
                $section = $lockedQuestion->section;
                $lockedQuestion->delete();
                $order->normalizeQuestions($section);

                return $lockedCampaign;
            },
        );

        $this->flashSuccessToast(__('Question removed from campaign.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    public function reorder(
        ReorderCampaignQuestionsRequest $request,
        Campaign $campaign,
        CampaignSection $section,
        CampaignLifecycleService $lifecycle,
        CampaignDefinitionOrder $order,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $section, $order): Campaign {
                $order->questions($lockedCampaign, $section, $request->validated('question_ids'));

                return $lockedCampaign;
            },
        );

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
