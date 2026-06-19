<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignSectionRequest;
use App\Models\Campaign;
use App\Models\CampaignSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignSectionController extends Controller
{
    /**
     * Store a section in the campaign.
     */
    public function store(StoreCampaignSectionRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->sections()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section added.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Delete a campaign section and its questions.
     */
    public function destroy(Campaign $campaign, CampaignSection $section): RedirectResponse
    {
        $this->ensureSectionBelongsToCampaign($campaign, $section);

        $section->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    private function ensureSectionBelongsToCampaign(Campaign $campaign, CampaignSection $section): void
    {
        if ($section->campaign_id !== $campaign->id) {
            throw ValidationException::withMessages([
                'section' => __('The selected section does not belong to this campaign.'),
            ]);
        }
    }
}
