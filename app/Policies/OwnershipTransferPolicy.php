<?php

namespace App\Policies;

use App\Models\OwnershipTransfer;
use App\Models\User;
use App\OwnershipTransferStatus;
use App\TeamMembershipRole;
use App\TeamStatus;

class OwnershipTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function revoke(User $user, OwnershipTransfer $ownershipTransfer): bool
    {
        return $ownershipTransfer->status === OwnershipTransferStatus::Pending
            && $ownershipTransfer->team->status === TeamStatus::Active
            && $user->current_team_id === $ownershipTransfer->team_id
            && $user->activeTeamMemberships()
                ->where('team_id', $ownershipTransfer->team_id)
                ->where('role', TeamMembershipRole::Owner)
                ->exists();
    }
}
