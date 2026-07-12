<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountClosureService
{
    public function __construct(private TeamActivityRecorder $activities) {}

    public function close(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $teamIds = $user->activeTeamMemberships()
                ->pluck('team_id')
                ->sort()
                ->values();
            $teams = Team::query()
                ->whereKey($teamIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $memberships = $lockedUser->activeTeamMemberships()->lockForUpdate()->get();

            if ($memberships->contains(fn (TeamMembership $membership): bool => $membership->role === TeamMembershipRole::Owner
                && $teams->has($membership->team_id))) {
                throw ValidationException::withMessages([
                    'account' => __('Transfer Ownership of every Team you own before closing your account.'),
                ]);
            }

            foreach ($memberships as $membership) {
                $team = $teams->get($membership->team_id);

                if ($team === null) {
                    continue;
                }

                $membership->update(['ended_at' => now()]);
                $this->activities->record(
                    $team,
                    $lockedUser,
                    'account_closed_membership_ended',
                    $membership,
                    before: ['role' => $membership->role->value, 'ended_at' => null],
                    after: ['role' => $membership->role->value, 'ended_at' => $membership->ended_at->toISOString()],
                );
            }

            $lockedUser->platformOperatorAuthorities()
                ->active()
                ->update(['revoked_at' => now()]);

            DB::table((string) config('session.table', 'sessions'))->where('user_id', $lockedUser->id)->delete();

            $lockedUser->forceFill([
                'current_team_id' => null,
                'remember_token' => null,
            ])->save();
            $lockedUser->delete();
        });
    }
}
