<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignCloneController extends Controller
{
    /**
     * Clone the campaign definition into a new same-Team draft.
     */
    public function store(Request $request, Campaign $campaign, CampaignLifecycleService $lifecycle): RedirectResponse
    {
        $clone = $lifecycle->cloneToDraft($campaign, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign cloned as a new draft.')]);

        return to_route('admin.campaigns.show', $clone);
    }
}
