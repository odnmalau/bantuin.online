<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\UpdateTeamLifecycleRequest;
use App\Models\Team;
use App\Services\TeamLifecycleService;
use Illuminate\Http\RedirectResponse;

class TeamLifecycleController extends Controller
{
    public function deactivate(UpdateTeamLifecycleRequest $request, Team $team, TeamLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->deactivateByOperator($team, $request->user(), $request->string('reason')->trim()->toString());

        return back();
    }

    public function reactivate(UpdateTeamLifecycleRequest $request, Team $team, TeamLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->reactivateByOperator($team, $request->user(), $request->string('reason')->trim()->toString());

        return back();
    }
}
