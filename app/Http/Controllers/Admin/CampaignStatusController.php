<?php

namespace App\Http\Controllers\Admin;

use App\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignLifecycleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignStatusController extends Controller
{
    public function __construct(private CampaignLifecycleService $lifecycle) {}

    /**
     * Archive the campaign.
     */
    public function archive(Campaign $campaign): RedirectResponse
    {
        $campaign = $this->lifecycle->archive($campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign archived.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Move the campaign back to draft.
     */
    public function draft(Campaign $campaign): RedirectResponse
    {
        $campaign = $this->lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign): Campaign {
                $lockedCampaign->update([
                    'status' => CampaignStatus::Draft,
                    'activated_at' => null,
                ]);

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign moved to draft.')]);

        return to_route('admin.campaigns.show', $campaign);
    }
}
