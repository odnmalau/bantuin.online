<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMembershipRequest;
use App\Models\TeamMembership;
use App\Services\TeamMembershipService;
use App\TeamMembershipRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMembershipController extends Controller
{
    public function update(
        UpdateTeamMembershipRequest $request,
        TeamMembership $teamMembership,
        TeamMembershipService $memberships,
    ): RedirectResponse {
        $memberships->changeRole(
            $teamMembership,
            TeamMembershipRole::from($request->validated('role')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team Member role updated.')]);

        return back();
    }

    public function destroy(TeamMembership $teamMembership, TeamMembershipService $memberships): RedirectResponse
    {
        Gate::authorize('remove', $teamMembership);
        $memberships->end($teamMembership, request()->user(), 'team_membership_removed');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team Membership ended.')]);

        return back();
    }

    public function leave(TeamMembershipService $memberships): RedirectResponse
    {
        $user = request()->user();
        $membership = $user->activeTeamMemberships()
            ->where('team_id', $user->current_team_id)
            ->firstOrFail();
        Gate::authorize('leave', $membership);
        $memberships->end($membership, $user, 'team_membership_departed');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the Team.')]);

        return to_route('dashboard');
    }
}
