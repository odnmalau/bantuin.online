<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\OwnershipTransferStatus;
use App\Services\TeamLifecycleService;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function edit(Request $request, TeamLifecycleService $lifecycle): Response
    {
        $user = $request->user();
        $team = $user->currentTeam()->firstOrFail();
        $viewerMembership = $user->activeTeamMemberships()->whereBelongsTo($team)->firstOrFail();
        $canViewActivity = $user->can('viewActivity', $team);
        $viewerRole = $viewerMembership->role;
        $deletionBlocker = $lifecycle->emptyTeamDeletionBlocker($team);
        $canDelete = $user->can('delete', $team) && $deletionBlocker === null;
        $members = $team->activeMemberships()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get();

        $props = [
            'team' => $team->only(['id', 'name', 'status']),
            'members' => $members->map(fn (TeamMembership $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'can_change_role' => $user->can('changeRole', $membership),
                'can_remove' => $user->can('remove', $membership),
            ])->all(),
            'invitations' => $canViewActivity
                ? $team->invitations()
                    ->whereIn('status', [TeamInvitationStatus::Pending, TeamInvitationStatus::Expired])
                    ->latest()
                    ->get()
                    ->map(fn (TeamInvitation $invitation): array => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => $invitation->role->value,
                        'status' => $invitation->status->value,
                        'expires_at' => $invitation->expires_at->toISOString(),
                        'can_revoke' => $user->can('revoke', $invitation),
                        'can_resend' => $user->can('resend', $invitation),
                    ])->all()
                : [],
            'pendingTransfer' => $this->pendingTransfer($team),
            'can' => [
                'inviteAdministrator' => $user->can('invite', [$team, TeamMembershipRole::Administrator]),
                'inviteCollaborator' => $user->can('invite', [$team, TeamMembershipRole::Collaborator]),
                'transferOwnership' => $user->can('transferOwnership', $team),
                'viewActivity' => $canViewActivity,
                'leave' => $user->can('leave', $viewerMembership),
                'deactivate' => $user->can('deactivate', $team),
                'reactivate' => $user->can('reactivate', $team),
                'delete' => $canDelete,
            ],
            'deletionBlocker' => $viewerRole === TeamMembershipRole::Owner ? $deletionBlocker : null,
        ];

        if ($canViewActivity) {
            $activityPage = $team->activities()
                ->with('actor:id,name')
                ->latest('occurred_at')
                ->paginate(50)
                ->withQueryString();
            $props['activities'] = $activityPage->getCollection()
                ->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'actor_name' => $activity->actor?->name ?? __('System'),
                    'action' => $activity->action,
                    'actor_context' => $activity->actor_context,
                    'reason' => $activity->reason,
                    'before' => $activity->before_state,
                    'after' => $activity->after_state,
                    'occurred_at' => $activity->occurred_at->toISOString(),
                ])->all();
            $props['activityPagination'] = [
                'previous' => $activityPage->previousPageUrl(),
                'next' => $activityPage->nextPageUrl(),
            ];
        }

        return Inertia::render('settings/team', $props);
    }

    /** @return array<string, mixed>|null */
    private function pendingTransfer(Team $team): ?array
    {
        $transfer = $team->ownershipTransfers()
            ->where('status', OwnershipTransferStatus::Pending)
            ->with('recipientMembership.user:id,name,email')
            ->first();

        if (! $transfer instanceof OwnershipTransfer) {
            return null;
        }

        return [
            'id' => $transfer->id,
            'recipient_name' => $transfer->recipientMembership->user->name,
            'recipient_email' => $transfer->recipientMembership->user->email,
            'expires_at' => $transfer->expires_at->toISOString(),
        ];
    }
}
