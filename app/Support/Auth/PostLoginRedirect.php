<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Services\CampaignInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostLoginRedirect
{
    public function __construct(private CampaignInvitationService $invitations) {}

    /**
     * Redirect after authentication, avoiding role-protected URLs the user cannot access.
     */
    public function toResponse(Request $request, User $user): RedirectResponse
    {
        $invitationRedirect = $this->invitations->completePendingRedemption($request, $user);

        if ($invitationRedirect !== null) {
            return $invitationRedirect;
        }

        $fallback = route('dashboard', absolute: false);
        $intended = $request->session()->get('url.intended');

        if ($intended !== null && $this->userCanAccessUrl($user, $intended)) {
            return redirect()->intended($fallback);
        }

        $request->session()->forget('url.intended');

        return redirect($fallback);
    }

    private function userCanAccessUrl(User $user, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_starts_with($path, '/admin')) {
            return $user->current_team_id !== null
                && $user->activeTeamMemberships()->where('team_id', $user->current_team_id)->exists();
        }

        if (str_starts_with($path, '/candidate')) {
            return true;
        }

        return true;
    }
}
