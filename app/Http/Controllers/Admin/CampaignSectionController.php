<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignSectionRequest;
use App\Models\Campaign;
use App\Models\CampaignSection;
use App\Services\CampaignLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignSectionController extends Controller
{
    /**
     * Store a section in the campaign.
     */
    public function store(
        StoreCampaignSectionRequest $request,
        Campaign $campaign,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request): Campaign {
                $lockedCampaign->sections()->create($request->validated());

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section added.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Delete a campaign section and its questions.
     */
    public function destroy(
        Campaign $campaign,
        CampaignSection $section,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($section): Campaign {
                $lockedSection = CampaignSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
                $this->ensureSectionBelongsToCampaign($lockedCampaign, $lockedSection);
                $lockedSection->delete();

                return $lockedCampaign;
            },
        );

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
