<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $team = Team::createForOwner($user, $request->validated('name'));

            $user->selectCurrentTeam($team);
            $this->recordActivity($team, $user, 'team_created', null, ['name' => $team->name]);
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

            $this->recordActivity($lockedTeam, $request->user(), 'team_renamed', $before, ['name' => $lockedTeam->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team renamed.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function recordActivity(Team $team, User $actor, string $action, ?array $before, array $after): void
    {
        TeamActivity::query()->create([
            'team_id' => $team->id,
            'actor_id' => $actor->id,
            'actor_context' => 'team_member',
            'action' => $action,
            'subject_type' => Team::class,
            'subject_id' => $team->id,
            'before_state' => $before,
            'after_state' => $after,
            'occurred_at' => now(),
        ]);
    }
}
