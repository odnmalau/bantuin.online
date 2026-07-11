<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamMembershipService
{
    public function __construct(private TeamActivityRecorder $activities) {}

    public function changeRole(TeamMembership $membership, TeamMembershipRole $role, User $actor): void
    {
        DB::transaction(function () use ($membership, $role, $actor): void {
            $lockedMembership = TeamMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($lockedMembership->team_id)->lockForUpdate()->firstOrFail();
            $actorMembership = TeamMembership::query()
                ->active()
                ->where('team_id', $team->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if ($team->status !== TeamStatus::Active
                || ! $lockedMembership->isActive()
                || $lockedMembership->role === TeamMembershipRole::Owner
                || $actorMembership?->role !== TeamMembershipRole::Owner
                || ! in_array($role, [TeamMembershipRole::Administrator, TeamMembershipRole::Collaborator], true)) {
                throw ValidationException::withMessages([
                    'role' => __('This Team Membership role can no longer be changed.'),
                ]);
            }

            $before = ['role' => $lockedMembership->role->value];
            $lockedMembership->update(['role' => $role]);

            $this->activities->record(
                $lockedMembership->team,
                $actor,
                'team_membership_role_changed',
                $lockedMembership,
                before: $before,
                after: ['role' => $role->value, 'user_id' => $lockedMembership->user_id],
            );
        });
    }

    public function end(TeamMembership $membership, User $actor, string $action): void
    {
        DB::transaction(function () use ($membership, $actor, $action): void {
            $lockedMembership = TeamMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($lockedMembership->team_id)->lockForUpdate()->firstOrFail();
            $actorMembership = TeamMembership::query()
                ->active()
                ->where('team_id', $team->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            $mayDepart = $action === 'team_membership_departed'
                && $lockedMembership->user_id === $actor->id
                && $lockedMembership->role !== TeamMembershipRole::Owner;
            $mayRemove = $action === 'team_membership_removed'
                && $lockedMembership->role !== TeamMembershipRole::Owner
                && ($actorMembership?->role === TeamMembershipRole::Owner
                    || ($actorMembership?->role === TeamMembershipRole::Administrator
                        && $lockedMembership->role === TeamMembershipRole::Collaborator));

            if ($team->status !== TeamStatus::Active
                || ! $lockedMembership->isActive()
                || (! $mayDepart && ! $mayRemove)) {
                throw ValidationException::withMessages([
                    'membership' => __('This Team Membership can no longer be ended.'),
                ]);
            }

            $member = User::query()->whereKey($lockedMembership->user_id)->lockForUpdate()->firstOrFail();
            $lockedMembership->update(['ended_at' => now()]);
            $member->replaceCurrentTeamAfterMembershipEnds($lockedMembership->team_id);

            $this->activities->record(
                $lockedMembership->team,
                $actor,
                $action,
                $lockedMembership,
                before: ['role' => $lockedMembership->role->value, 'active' => true],
                after: ['role' => $lockedMembership->role->value, 'active' => false, 'user_id' => $member->id],
            );
        });
    }
}
