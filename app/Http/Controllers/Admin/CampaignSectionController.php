<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderCampaignSectionsRequest;
use App\Http\Requests\Admin\StoreCampaignSectionRequest;
use App\Http\Requests\Admin\UpdateCampaignSectionRequest;
use App\Models\Campaign;
use App\Models\CampaignSection;
use App\Services\CampaignDefinitionOrder;
use App\Services\CampaignLifecycleService;
use App\Services\CampaignSectionDistribution;
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
        CampaignSectionDistribution $distribution,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $distribution): Campaign {
                $distribution->create($lockedCampaign, $request->validated());

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section added.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Update a campaign section.
     */
    public function update(
        UpdateCampaignSectionRequest $request,
        Campaign $campaign,
        CampaignSection $section,
        CampaignLifecycleService $lifecycle,
        CampaignSectionDistribution $distribution,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $section, $distribution): Campaign {
                $lockedSection = CampaignSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
                $this->ensureSectionBelongsToCampaign($lockedCampaign, $lockedSection);
                $distribution->update($lockedCampaign, $lockedSection, $request->validated());

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Delete a campaign section and its questions.
     */
    public function destroy(
        Campaign $campaign,
        CampaignSection $section,
        CampaignLifecycleService $lifecycle,
        CampaignSectionDistribution $distribution,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($section, $distribution): Campaign {
                $lockedSection = CampaignSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
                $this->ensureSectionBelongsToCampaign($lockedCampaign, $lockedSection);
                $lockedSection->delete();
                $distribution->normalize($lockedCampaign);

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    public function reorder(
        ReorderCampaignSectionsRequest $request,
        Campaign $campaign,
        CampaignLifecycleService $lifecycle,
        CampaignDefinitionOrder $order,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request, $order): Campaign {
                $order->sections($lockedCampaign, $request->validated('section_ids'));

                return $lockedCampaign;
            },
        );

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
