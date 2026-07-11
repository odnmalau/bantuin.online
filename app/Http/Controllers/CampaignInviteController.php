<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CampaignInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CampaignInviteController extends Controller
{
    /**
     * Begin redeeming a campaign exam invitation from an invite link.
     */
    public function show(
        Request $request,
        string $token,
        CampaignInvitationService $invitations,
    ): RedirectResponse {
        $invitation = $invitations->findByPlainToken($token);

        if ($invitation === null || ! $invitation->isRedeemable()) {
            return $this->redirectToLogin(__('This invitation link is invalid or has expired.'));
        }

        $invitations->rememberPendingInvitation($request, $invitation);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->redirectToLogin(__('Sign in with Google using :email to start your assessment.', [
                'email' => $invitation->email,
            ]));
        }

        try {
            $invitation = $invitations->acceptForUser($invitation, $user);
        } catch (ValidationException $exception) {
            /** @var string|null $status */
            $status = collect($exception->errors())->flatten()->first();

            return $this->redirectToLogin($status);
        }

        $invitations->forgetPendingInvitation($request);

        return redirect()->route('candidate.campaigns.exam', $invitation->campaign_id);
    }

    private function redirectToLogin(?string $status): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', $status);
    }
}
