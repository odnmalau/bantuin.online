<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCampaignRankingRequest;
use App\Models\Campaign;
use App\Services\CampaignLifecycleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignRankingController extends Controller
{
    /**
     * Update the campaign ranking weights.
     */
    public function update(
        UpdateCampaignRankingRequest $request,
        Campaign $campaign,
        CampaignLifecycleService $lifecycle,
    ): RedirectResponse {
        $campaign = $lifecycle->withEditableDefinition(
            $campaign,
            function (Campaign $lockedCampaign) use ($request): Campaign {
                $lockedCampaign->update($request->validated());

                return $lockedCampaign;
            },
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ranking weights updated.')]);

        return to_route('admin.campaigns.show', $campaign);
    }
}
