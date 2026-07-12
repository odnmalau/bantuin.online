<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreMembershipRepairRequest;
use App\Models\Team;
use App\Services\TeamInvitationService;
use App\TeamMembershipRole;
use Illuminate\Http\RedirectResponse;

class TeamMembershipRepairController extends Controller
{
    public function store(StoreMembershipRepairRequest $request, Team $team, TeamInvitationService $invitations): RedirectResponse
    {
        $invitations->issueByOperator($team, $request->user(), $request->string('email')->toString(), TeamMembershipRole::from($request->string('role')->toString()), $request->string('reason')->trim()->toString());

        return back();
    }
}
