<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\TeamStatus;

class CampaignPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentMembership($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        return $campaign->team_id === $user->current_team_id
            && $this->hasCurrentMembership($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canMutateCurrentTeam($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign)
            && $this->canMutateCurrentTeam($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Campaign $campaign): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return false;
    }

    private function hasCurrentMembership(User $user): bool
    {
        return $user->current_team_id !== null
            && $user->activeTeamMemberships()->where('team_id', $user->current_team_id)->exists();
    }

    private function canMutateCurrentTeam(User $user): bool
    {
        return $this->hasCurrentMembership($user)
            && $user->currentTeam()->where('status', TeamStatus::Active)->exists();
    }
}
