<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignInvitationRequest;
use App\Models\Campaign;
use App\Services\CampaignInvitationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CampaignInvitationController extends Controller
{
    /**
     * Create or refresh a campaign exam invitation.
     */
    public function store(
        StoreCampaignInvitationRequest $request,
        Campaign $campaign,
        CampaignInvitationService $invitations,
    ): RedirectResponse {
        $result = $invitations->create(
            campaign: $campaign,
            email: $request->validated('email'),
            invitedBy: $request->user(),
            sendEmail: $request->boolean('send_email', true),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exam invitation created for :email.', [
            'email' => $result['invitation']->email,
        ])]);

        return back()->with('campaign_invite_url', $result['invite_url']);
    }
}
