<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function __construct(private TeamActivityRecorder $activities) {}

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $team = Team::createForOwner($user, $request->validated('name'));

            $user->selectCurrentTeam($team);
            $this->activities->record($team, $user, 'team_created', $team, after: ['name' => $team->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('dashboard');
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $before = ['name' => $lockedTeam->name];
            $lockedTeam->update($request->validated());

            $this->activities->record(
                $lockedTeam,
                $request->user(),
                'team_renamed',
                $lockedTeam,
                before: $before,
                after: ['name' => $lockedTeam->name],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team renamed.')]);

        return back();
    }
}
