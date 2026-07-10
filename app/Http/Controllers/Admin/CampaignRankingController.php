<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCampaignRankingRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignRankingController extends Controller
{
    /**
     * Update the campaign ranking weights.
     */
    public function update(UpdateCampaignRankingRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ranking weights updated.')]);

        return to_route('admin.campaigns.show', $campaign);
    }
}
