<?php

namespace App\Http\Controllers\Admin;

use App\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignStatusController extends Controller
{
    /**
     * Archive the campaign.
     */
    public function archive(Campaign $campaign): RedirectResponse
    {
        $campaign->update([
            'status' => CampaignStatus::Archived,
            'activated_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign archived.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Move the campaign back to draft.
     */
    public function draft(Campaign $campaign): RedirectResponse
    {
        $campaign->update([
            'status' => CampaignStatus::Draft,
            'activated_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign moved to draft.')]);

        return to_route('admin.campaigns.show', $campaign);
    }
}
