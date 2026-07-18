<?php

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\TeamCapability;

class TeamInvitationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function revoke(User $user, TeamInvitation $teamInvitation): bool
    {
        return ! $teamInvitation->team->isDemo()
            && TeamCapability::canRevokeInvitation($user, $teamInvitation);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function resend(User $user, TeamInvitation $teamInvitation): bool
    {
        return ! $teamInvitation->team->isDemo()
            && TeamCapability::canResendInvitation($user, $teamInvitation);
    }
}
