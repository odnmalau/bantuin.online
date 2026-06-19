<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBankQuestionToCampaignRequest;
use App\Models\BankQuestion;
use App\Models\Campaign;
use App\QuestionStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignQuestionImportController extends Controller
{
    /**
     * Import a reusable library question into a campaign section as a snapshot.
     */
    public function store(ImportBankQuestionToCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validated();
        $bankQuestion = BankQuestion::query()->findOrFail($validated['bank_question_id']);

        $campaign->questions()->create([
            ...$bankQuestion->only([
                'type',
                'grading_mode',
                'prompt',
                'options',
                'correct_answer',
                'expected_rubric',
                'points',
                'difficulty',
                'skill_tags',
                'ai_generated',
            ]),
            'campaign_section_id' => $validated['campaign_section_id'],
            'source_bank_question_id' => $bankQuestion->id,
            'status' => QuestionStatus::Approved,
            'is_required' => $validated['is_required'],
            'sort_order' => $validated['sort_order'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question imported into campaign.')]);

        return to_route('admin.campaigns.show', $campaign);
    }
}
