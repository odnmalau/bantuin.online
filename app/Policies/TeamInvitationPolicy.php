<?php

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use App\TeamStatus;

class TeamInvitationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function revoke(User $user, TeamInvitation $teamInvitation): bool
    {
        return $teamInvitation->status === TeamInvitationStatus::Pending
            && $this->canManage($user, $teamInvitation);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function resend(User $user, TeamInvitation $teamInvitation): bool
    {
        return in_array($teamInvitation->status, [TeamInvitationStatus::Pending, TeamInvitationStatus::Expired], true)
            && $this->canManage($user, $teamInvitation);
    }

    /**
     * Determine whether the user can create models.
     */
    private function canManage(User $user, TeamInvitation $teamInvitation): bool
    {
        if ($user->current_team_id !== $teamInvitation->team_id
            || $teamInvitation->team->status !== TeamStatus::Active) {
            return false;
        }

        $role = $user->activeTeamMemberships()
            ->where('team_id', $teamInvitation->team_id)
            ->first()
            ?->role;

        return $role === TeamMembershipRole::Owner
            || ($role === TeamMembershipRole::Administrator
                && $teamInvitation->role === TeamMembershipRole::Collaborator);
    }
}
