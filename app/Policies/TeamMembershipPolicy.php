<?php

namespace App\Policies;

use App\Models\TeamMembership;
use App\Models\User;
use App\Support\TeamCapability;
use App\TeamMembershipRole;
use App\TeamStatus;

class TeamMembershipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function changeRole(User $user, TeamMembership $teamMembership): bool
    {
        return ! $teamMembership->team->isDemo()
            && TeamCapability::canChangeRole($user, $teamMembership);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function remove(User $user, TeamMembership $teamMembership): bool
    {
        return ! $teamMembership->team->isDemo()
            && TeamCapability::canRemove($user, $teamMembership);
    }

    /**
     * Determine whether the user can create models.
     */
    public function leave(User $user, TeamMembership $teamMembership): bool
    {
        return ! $teamMembership->team->isDemo()
            && $teamMembership->user_id === $user->id
            && $teamMembership->isActive()
            && $teamMembership->role !== TeamMembershipRole::Owner
            && $user->current_team_id === $teamMembership->team_id
            && $teamMembership->team->status === TeamStatus::Active;
    }
}
