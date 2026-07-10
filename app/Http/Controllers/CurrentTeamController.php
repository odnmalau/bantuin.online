<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurrentTeamController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['team_id' => ['required', 'integer']]);
        $team = Team::query()
            ->whereKey($validated['team_id'])
            ->whereHas('activeMemberships', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        $request->user()->selectCurrentTeam($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Current Team changed.')]);

        return to_route('dashboard');
    }
}
