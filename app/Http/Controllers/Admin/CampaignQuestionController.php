<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Admin\Concerns\ValidatesDraftQuestionAiMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignQuestionRequest;
use App\Http\Requests\Admin\UpdateCampaignQuestionRequest;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\QuestionStatus;
use App\Services\Ai\QwenMcqOptionsRegenerator;
use App\Services\Ai\QwenTextQuestionToMcqConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CampaignQuestionController extends Controller
{
    use HandlesAssessmentGenerationFailures;
    use ValidatesDraftQuestionAiMutations;

    /**
     * Store a campaign question snapshot.
     */
    public function store(StoreCampaignQuestionRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->questions()->create($request->validated());

        $this->flashSuccessToast(__('Question added to campaign.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Update a campaign question snapshot without mutating its source library question.
     */
    public function update(UpdateCampaignQuestionRequest $request, Campaign $campaign, CampaignQuestion $question): RedirectResponse
    {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $question->update($request->validated());

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
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);
        $this->ensureDraftMcqRegeneration($question->type, $question->status);

        $result = $this->runAssessmentGeneration(
            'regeneration',
            fn () => $regenerator->regenerateForCampaignQuestion($question, $campaign),
        );

        $question->update([
            'options' => $result->options,
            'correct_answer' => $result->correctAnswer,
        ]);

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
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);
        $this->ensureDraftMcqConversion($question->type, $question->status);

        $result = $this->runAssessmentGeneration(
            'conversion',
            fn () => $converter->convertCampaignQuestion($question, $campaign),
        );

        $question->update($this->attributesAfterMcqConversion($result));

        $this->flashSuccessToast(__('Question converted to multiple choice.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Approve a generated draft campaign question.
     */
    public function approve(Campaign $campaign, CampaignQuestion $question): RedirectResponse
    {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $this->ensureDraftQuestionStatus(
            $question->status,
            'question',
            __('Only draft questions can be approved.'),
        );

        $question->update([
            'status' => QuestionStatus::Approved,
        ]);

        $this->flashSuccessToast(__('Question approved.'));

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Approve all generated draft questions in the campaign.
     */
    public function approveAll(Campaign $campaign): RedirectResponse
    {
        $approvedQuestions = $campaign->questions()
            ->where('status', QuestionStatus::Draft->value)
            ->update([
                'status' => QuestionStatus::Approved,
            ]);

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
    public function destroy(Campaign $campaign, CampaignQuestion $question): RedirectResponse
    {
        $this->ensureQuestionBelongsToCampaign($campaign, $question);

        $question->delete();

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
