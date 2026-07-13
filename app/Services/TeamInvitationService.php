<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Support\TeamCapability;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    public const SESSION_PENDING_ID = 'team_invitation.pending_id';

    public function __construct(private TeamActivityRecorder $activities) {}

    public function issue(Team $team, User $inviter, string $email, TeamMembershipRole $role): TeamInvitation
    {
        return $this->issueAs($team, $inviter, $email, $role, 'team_member');
    }

    public function issueByOperator(Team $team, User $operator, string $email, TeamMembershipRole $role, string $reason): TeamInvitation
    {
        $this->assertSupportReason($reason);

        return $this->issueAs($team, $operator, $email, $role, 'platform_operator', $reason);
    }

    private function issueAs(Team $team, User $inviter, string $email, TeamMembershipRole $role, string $actorContext, ?string $reason = null): TeamInvitation
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($team, $inviter, $normalizedEmail, $role, $plainToken, $actorContext, $reason): TeamInvitation {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $inviterRole = TeamMembership::query()
                ->active()
                ->where('team_id', $lockedTeam->id)
                ->where('user_id', $inviter->id)
                ->lockForUpdate()
                ->first()
                ?->role;

            $isAuthorizedOperator = $actorContext === 'platform_operator' && $inviter->isPlatformOperator();

            if ($lockedTeam->status !== TeamStatus::Active
                || (! $isAuthorizedOperator && ! TeamCapability::canManageRole($inviterRole, $role))) {
                throw ValidationException::withMessages([
                    'invitation' => __('You are no longer authorized to issue this Team Invitation.'),
                ]);
            }

            if (TeamInvitation::query()
                ->whereBelongsTo($lockedTeam)
                ->where('email', $normalizedEmail)
                ->where('status', TeamInvitationStatus::Pending)
                ->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('A pending Team Invitation already exists for this email address.'),
                ]);
            }

            $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if ($existingUser?->activeTeamMemberships()->whereBelongsTo($lockedTeam)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('This person is already an active Team Member.'),
                ]);
            }

            $hasCandidateHistory = $lockedTeam->campaigns()
                ->whereHas('invitations', function ($query) use ($normalizedEmail, $existingUser): void {
                    $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]);

                    if ($existingUser !== null) {
                        $query->orWhere('user_id', $existingUser->id);
                    }
                })
                ->exists();

            if ($hasCandidateHistory) {
                throw ValidationException::withMessages([
                    'email' => __('Candidate history in this Team prevents Team Membership.'),
                ]);
            }

            $invitation = TeamInvitation::query()->create([
                'team_id' => $lockedTeam->id,
                'email' => $normalizedEmail,
                'role' => $role,
                'invited_by' => $inviter->id,
                'actor_context' => $actorContext,
                'token_hash' => hash('sha256', $plainToken),
                'status' => TeamInvitationStatus::Pending,
                'expires_at' => now()->addDays(14),
            ]);

            $this->activities->record(
                $lockedTeam,
                $inviter,
                'team_invitation_issued',
                $invitation,
                before: [],
                after: ['email' => $normalizedEmail, 'role' => $role->value],
                actorContext: $actorContext,
                reason: $reason,
            );

            return $invitation;
        });

        Notification::route('mail', $normalizedEmail)
            ->notify((new TeamInvitationNotification($invitation, $plainToken))->afterCommit());

        return $invitation;
    }

    public function findByPlainToken(string $plainToken): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }

    private function assertSupportReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('A support reason is required.')]);
        }
    }

    public function accept(TeamInvitation $invitation, User $recipient): TeamMembership
    {
        return DB::transaction(function () use ($invitation, $recipient): TeamMembership {
            $lockedInvitation = TeamInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $team = Team::query()->whereKey($lockedInvitation->team_id)->lockForUpdate()->firstOrFail();

            if ($lockedInvitation->status === TeamInvitationStatus::Pending
                && $lockedInvitation->expires_at->isPast()) {
                $lockedInvitation->update(['status' => TeamInvitationStatus::Expired]);
            }

            if (! $lockedInvitation->isRedeemable()) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Team Invitation is no longer available.'),
                ]);
            }

            if ($team->status !== TeamStatus::Active) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Team is not accepting membership changes.'),
                ]);
            }

            if (mb_strtolower($recipient->email) !== $lockedInvitation->email) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Team Invitation was sent to a different email address.'),
                ]);
            }

            $inviterRole = TeamMembership::query()
                ->active()
                ->where('team_id', $team->id)
                ->where('user_id', $lockedInvitation->invited_by)
                ->lockForUpdate()
                ->first()
                ?->role;
            $inviterIsAuthorized = $lockedInvitation->actor_context === 'platform_operator'
                ? User::query()->find($lockedInvitation->invited_by)?->isPlatformOperator() === true
                : TeamCapability::canManageRole($inviterRole, $lockedInvitation->role);

            if (! $inviterIsAuthorized) {
                throw ValidationException::withMessages([
                    'invitation' => __('The inviter is no longer authorized to offer this Team role.'),
                ]);
            }

            $hasCandidateHistory = $team->campaigns()
                ->whereHas('invitations', fn ($query) => $query
                    ->where('user_id', $recipient->id)
                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($recipient->email)]))
                ->exists();

            if ($hasCandidateHistory) {
                throw ValidationException::withMessages([
                    'invitation' => __('Candidate history in this Team prevents Team Membership.'),
                ]);
            }

            if (TeamMembership::query()->active()->whereBelongsTo($team)->whereBelongsTo($recipient)->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => __('This account is already an active Team Member.'),
                ]);
            }

            $membership = TeamMembership::query()->create([
                'team_id' => $team->id,
                'user_id' => $recipient->id,
                'role' => $lockedInvitation->role,
                'started_at' => now(),
            ]);

            $lockedInvitation->update([
                'status' => TeamInvitationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_by' => $recipient->id,
            ]);

            $this->activities->record(
                $team,
                $recipient,
                'team_invitation_accepted',
                $membership,
                after: ['user_id' => $recipient->id, 'role' => $membership->role->value],
            );

            $recipient->selectCurrentTeam($team);

            return $membership;
        });
    }

    public function revoke(TeamInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $lockedInvitation = TeamInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvitation->status !== TeamInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => __('Only pending Team Invitations may be revoked.'),
                ]);
            }

            $lockedInvitation->update([
                'status' => TeamInvitationStatus::Revoked,
                'revoked_at' => now(),
            ]);

            $this->activities->record(
                $lockedInvitation->team,
                $actor,
                'team_invitation_revoked',
                $lockedInvitation,
                before: ['status' => TeamInvitationStatus::Pending->value],
                after: ['status' => TeamInvitationStatus::Revoked->value],
            );
        });
    }

    public function completePendingRedemption(Request $request, User $recipient): ?RedirectResponse
    {
        $invitationId = $request->session()->pull(self::SESSION_PENDING_ID);

        if (! is_int($invitationId) && ! is_string($invitationId)) {
            return null;
        }

        $invitation = TeamInvitation::query()->find((int) $invitationId);

        if ($invitation === null) {
            return to_route('login')->with('status', __('This Team Invitation is no longer available.'));
        }

        try {
            $this->accept($invitation, $recipient);
        } catch (ValidationException $exception) {
            /** @var string|null $status */
            $status = collect($exception->errors())->flatten()->first();

            return to_route('login')->with('status', $status);
        }

        return to_route('team-settings.edit');
    }

    public function resend(TeamInvitation $invitation, User $actor): void
    {
        $plainToken = Str::random(64);

        $lockedInvitation = DB::transaction(function () use ($invitation, $actor, $plainToken): TeamInvitation {
            $lockedInvitation = TeamInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedInvitation->status, [TeamInvitationStatus::Pending, TeamInvitationStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Team Invitation cannot be resent.'),
                ]);
            }

            $before = ['status' => $lockedInvitation->status->value, 'expires_at' => $lockedInvitation->expires_at->toISOString()];
            $lockedInvitation->update([
                'token_hash' => hash('sha256', $plainToken),
                'status' => TeamInvitationStatus::Pending,
                'expires_at' => now()->addDays(14),
                'revoked_at' => null,
            ]);

            $this->activities->record(
                $lockedInvitation->team,
                $actor,
                'team_invitation_resent',
                $lockedInvitation,
                before: $before,
                after: ['status' => TeamInvitationStatus::Pending->value, 'expires_at' => $lockedInvitation->expires_at->toISOString()],
            );

            return $lockedInvitation;
        });

        Notification::route('mail', $lockedInvitation->email)
            ->notify((new TeamInvitationNotification($lockedInvitation, $plainToken))->afterCommit());
    }
}
