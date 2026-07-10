<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\TeamMembershipRole;
use App\TeamStatus;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->activeTeamMemberships()->where('team_id', $team->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        if ($team->status !== TeamStatus::Active || $user->current_team_id !== $team->id) {
            return false;
        }

        return $user->activeTeamMemberships()
            ->where('team_id', $team->id)
            ->whereIn('role', [TeamMembershipRole::Owner, TeamMembershipRole::Administrator])
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Team $team): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return false;
    }
}
