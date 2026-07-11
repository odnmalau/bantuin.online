<?php

namespace App\Policies;

use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use App\TeamStatus;

class TeamMembershipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function changeRole(User $user, TeamMembership $teamMembership): bool
    {
        return $this->isCurrentActiveTeam($user, $teamMembership)
            && $teamMembership->isActive()
            && $teamMembership->role !== TeamMembershipRole::Owner
            && $this->actorRole($user, $teamMembership) === TeamMembershipRole::Owner;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function remove(User $user, TeamMembership $teamMembership): bool
    {
        if (! $this->isCurrentActiveTeam($user, $teamMembership)
            || ! $teamMembership->isActive()
            || $teamMembership->role === TeamMembershipRole::Owner) {
            return false;
        }

        $actorRole = $this->actorRole($user, $teamMembership);

        return $actorRole === TeamMembershipRole::Owner
            || ($actorRole === TeamMembershipRole::Administrator
                && $teamMembership->role === TeamMembershipRole::Collaborator);
    }

    /**
     * Determine whether the user can create models.
     */
    public function leave(User $user, TeamMembership $teamMembership): bool
    {
        return $teamMembership->user_id === $user->id
            && $teamMembership->isActive()
            && $teamMembership->role !== TeamMembershipRole::Owner
            && $this->isCurrentActiveTeam($user, $teamMembership);
    }

    /**
     * Determine whether the user can update the model.
     */
    private function isCurrentActiveTeam(User $user, TeamMembership $teamMembership): bool
    {
        return $user->current_team_id === $teamMembership->team_id
            && $teamMembership->team->status === TeamStatus::Active;
    }

    /**
     * Determine whether the user can delete the model.
     */
    private function actorRole(User $user, TeamMembership $teamMembership): ?TeamMembershipRole
    {
        return $user->activeTeamMemberships()
            ->where('team_id', $teamMembership->team_id)
            ->first()
            ?->role;
    }
}
