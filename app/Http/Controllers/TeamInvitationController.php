<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamInvitationRequest;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamInvitationService;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    public function store(StoreTeamInvitationRequest $request, TeamInvitationService $invitations): RedirectResponse
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $role = TeamMembershipRole::from($request->validated('role'));

        $invitations->issue($team, $request->user(), $request->validated('email'), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team Invitation sent.')]);

        return back();
    }

    public function show(Request $request, string $token, TeamInvitationService $invitations): RedirectResponse
    {
        $invitation = $invitations->findByPlainToken($token);

        if ($invitation?->status === TeamInvitationStatus::Pending && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => TeamInvitationStatus::Expired]);
        }

        if ($invitation === null || ! $invitation->isRedeemable()) {
            return to_route('login')->with('status', __('This Team Invitation link is invalid or has expired.'));
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            $request->session()->put(TeamInvitationService::SESSION_PENDING_ID, $invitation->id);

            return to_route('login')->with('status', __('Sign in with :email to review this Team Invitation.', [
                'email' => $invitation->email,
            ]));
        }

        try {
            $invitations->accept($invitation, $user);
        } catch (ValidationException $exception) {
            /** @var string|null $status */
            $status = collect($exception->errors())->flatten()->first();

            return to_route('login')->with('status', $status);
        }

        return to_route('team-settings.edit');
    }

    public function destroy(TeamInvitation $teamInvitation, TeamInvitationService $invitations): RedirectResponse
    {
        Gate::authorize('revoke', $teamInvitation);
        $invitations->revoke($teamInvitation, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team Invitation revoked.')]);

        return back();
    }

    public function resend(TeamInvitation $teamInvitation, TeamInvitationService $invitations): RedirectResponse
    {
        Gate::authorize('resend', $teamInvitation);
        $invitations->resend($teamInvitation, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team Invitation resent.')]);

        return back();
    }
}
