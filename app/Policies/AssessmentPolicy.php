<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;
use App\TeamStatus;

class AssessmentPolicy
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
    public function view(User $user, Assessment $assessment): bool
    {
        return $assessment->campaign()->where('team_id', $user->current_team_id)->exists()
            && $this->hasCurrentMembership($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assessment $assessment): bool
    {
        return $this->view($user, $assessment)
            && $user->currentTeam()->where('status', TeamStatus::Active)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assessment $assessment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assessment $assessment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assessment $assessment): bool
    {
        return false;
    }

    private function hasCurrentMembership(User $user): bool
    {
        return $user->current_team_id !== null
            && $user->activeTeamMemberships()->where('team_id', $user->current_team_id)->exists();
    }
}
