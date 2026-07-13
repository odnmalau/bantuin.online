<?php

namespace App\Support;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use App\TeamStatus;

final class TeamCapability
{
    /**
     * Owner may manage any target role; Administrator may manage Collaborator only.
     */
    public static function canManageRole(?TeamMembershipRole $actorRole, TeamMembershipRole $targetRole): bool
    {
        return $actorRole === TeamMembershipRole::Owner
            || ($actorRole === TeamMembershipRole::Administrator
                && $targetRole === TeamMembershipRole::Collaborator);
    }

    public static function canInvite(User $actor, Team $team, TeamMembershipRole $targetRole): bool
    {
        if ($team->status !== TeamStatus::Active || $actor->current_team_id !== $team->id) {
            return false;
        }

        return self::canManageRole(self::actorRoleOnTeam($actor, $team->id), $targetRole);
    }

    public static function canRemove(User $actor, TeamMembership $target): bool
    {
        if (! self::isCurrentActiveTeam($actor, $target)
            || ! $target->isActive()
            || $target->role === TeamMembershipRole::Owner) {
            return false;
        }

        return self::canManageRole(self::actorRoleOnTeam($actor, $target->team_id), $target->role);
    }

    public static function canChangeRole(User $actor, TeamMembership $target): bool
    {
        return self::isCurrentActiveTeam($actor, $target)
            && $target->isActive()
            && $target->role !== TeamMembershipRole::Owner
            && self::actorRoleOnTeam($actor, $target->team_id) === TeamMembershipRole::Owner;
    }

    public static function canRevokeInvitation(User $actor, TeamInvitation $invitation): bool
    {
        return $invitation->status === TeamInvitationStatus::Pending
            && self::canManageInvitation($actor, $invitation);
    }

    public static function canResendInvitation(User $actor, TeamInvitation $invitation): bool
    {
        return in_array($invitation->status, [TeamInvitationStatus::Pending, TeamInvitationStatus::Expired], true)
            && self::canManageInvitation($actor, $invitation);
    }

    private static function canManageInvitation(User $actor, TeamInvitation $invitation): bool
    {
        if ($actor->current_team_id !== $invitation->team_id
            || $invitation->team->status !== TeamStatus::Active) {
            return false;
        }

        return self::canManageRole(self::actorRoleOnTeam($actor, $invitation->team_id), $invitation->role);
    }

    private static function isCurrentActiveTeam(User $actor, TeamMembership $membership): bool
    {
        return $actor->current_team_id === $membership->team_id
            && $membership->team->status === TeamStatus::Active;
    }

    private static function actorRoleOnTeam(User $actor, int $teamId): ?TeamMembershipRole
    {
        return $actor->activeTeamMemberships()
            ->where('team_id', $teamId)
            ->first()
            ?->role;
    }
}
