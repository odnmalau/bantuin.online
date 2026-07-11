<?php

namespace App\Services;

use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Notifications\OwnershipTransferNotification;
use App\OwnershipTransferStatus;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnershipTransferService
{
    public const SESSION_PENDING_ID = 'ownership_transfer.pending_id';

    public function __construct(private TeamActivityRecorder $activities) {}

    public function propose(Team $team, TeamMembership $recipient, User $owner): OwnershipTransfer
    {
        $plainToken = Str::random(64);

        $transfer = DB::transaction(function () use ($team, $recipient, $owner, $plainToken): OwnershipTransfer {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $ownerMembership = $lockedTeam->ownerMembership()->lockForUpdate()->firstOrFail();
            $recipientMembership = TeamMembership::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();

            if ($lockedTeam->status !== TeamStatus::Active
                || $ownerMembership->user_id !== $owner->id
                || $recipientMembership->team_id !== $lockedTeam->id
                || ! $recipientMembership->isActive()
                || $recipientMembership->role === TeamMembershipRole::Owner) {
                throw ValidationException::withMessages([
                    'membership_id' => __('Ownership may only be offered to an active Team Member.'),
                ]);
            }

            $existing = OwnershipTransfer::query()
                ->whereBelongsTo($lockedTeam)
                ->where('status', OwnershipTransferStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->expires_at->isPast()) {
                $existing->update(['status' => OwnershipTransferStatus::Expired]);
                $existing = null;
            }

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'ownership_transfer' => __('This Team already has a pending Ownership Transfer.'),
                ]);
            }

            $transfer = OwnershipTransfer::query()->create([
                'team_id' => $lockedTeam->id,
                'owner_membership_id' => $ownerMembership->id,
                'recipient_membership_id' => $recipientMembership->id,
                'token_hash' => hash('sha256', $plainToken),
                'status' => OwnershipTransferStatus::Pending,
                'expires_at' => now()->addDays(7),
            ]);

            $this->activities->record(
                $lockedTeam,
                $owner,
                'ownership_transfer_proposed',
                $transfer,
                after: ['recipient_user_id' => $recipientMembership->user_id, 'expires_at' => $transfer->expires_at->toISOString()],
            );

            return $transfer;
        });

        $recipient->user->notify((new OwnershipTransferNotification($transfer, $plainToken))->afterCommit());

        return $transfer;
    }

    public function findByPlainToken(string $plainToken): ?OwnershipTransfer
    {
        return OwnershipTransfer::query()->where('token_hash', hash('sha256', $plainToken))->first();
    }

    public function accept(OwnershipTransfer $transfer, User $recipient): void
    {
        DB::transaction(function () use ($transfer, $recipient): void {
            $lockedTransfer = OwnershipTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($lockedTransfer->team_id)->lockForUpdate()->firstOrFail();
            $ownerMembership = TeamMembership::query()->whereKey($lockedTransfer->owner_membership_id)->lockForUpdate()->firstOrFail();
            $recipientMembership = TeamMembership::query()->whereKey($lockedTransfer->recipient_membership_id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status === OwnershipTransferStatus::Pending && $lockedTransfer->expires_at->isPast()) {
                $lockedTransfer->update(['status' => OwnershipTransferStatus::Expired]);
            }

            if (! $lockedTransfer->isRedeemable()
                || $team->status !== TeamStatus::Active
                || ! $ownerMembership->isActive()
                || $ownerMembership->role !== TeamMembershipRole::Owner
                || ! $recipientMembership->isActive()
                || $recipientMembership->user_id !== $recipient->id) {
                throw ValidationException::withMessages([
                    'ownership_transfer' => __('This Ownership Transfer is no longer available.'),
                ]);
            }

            TeamMembership::query()->whereKey($ownerMembership->id)->update(['role' => TeamMembershipRole::Administrator]);
            TeamMembership::query()->whereKey($recipientMembership->id)->update(['role' => TeamMembershipRole::Owner]);
            $lockedTransfer->update([
                'status' => OwnershipTransferStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $this->activities->record(
                $team,
                $recipient,
                'ownership_transfer_accepted',
                $lockedTransfer,
                before: ['owner_user_id' => $ownerMembership->user_id],
                after: ['owner_user_id' => $recipient->id, 'previous_owner_role' => TeamMembershipRole::Administrator->value],
            );

            $recipient->selectCurrentTeam($team);
        });
    }

    public function revoke(OwnershipTransfer $transfer, User $owner): void
    {
        DB::transaction(function () use ($transfer, $owner): void {
            $lockedTransfer = OwnershipTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($lockedTransfer->team_id)->lockForUpdate()->firstOrFail();
            $ownerMembership = $team->ownerMembership()->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== OwnershipTransferStatus::Pending
                || $team->status !== TeamStatus::Active
                || $ownerMembership->user_id !== $owner->id) {
                throw ValidationException::withMessages([
                    'ownership_transfer' => __('This Ownership Transfer can no longer be revoked.'),
                ]);
            }

            $lockedTransfer->update([
                'status' => OwnershipTransferStatus::Revoked,
                'revoked_at' => now(),
            ]);

            $this->activities->record(
                $lockedTransfer->team,
                $owner,
                'ownership_transfer_revoked',
                $lockedTransfer,
                before: ['status' => OwnershipTransferStatus::Pending->value],
                after: ['status' => OwnershipTransferStatus::Revoked->value],
            );
        });
    }

    public function completePendingRedemption(Request $request, User $recipient): ?RedirectResponse
    {
        $transferId = $request->session()->pull(self::SESSION_PENDING_ID);

        if (! is_int($transferId) && ! is_string($transferId)) {
            return null;
        }

        $transfer = OwnershipTransfer::query()->find((int) $transferId);

        if ($transfer === null || $transfer->recipientMembership->user_id !== $recipient->id) {
            return to_route('login')->with('status', __('This Ownership Transfer is not available to this account.'));
        }

        try {
            $this->accept($transfer, $recipient);
        } catch (ValidationException $exception) {
            /** @var string|null $status */
            $status = collect($exception->errors())->flatten()->first();

            return to_route('login')->with('status', $status);
        }

        return to_route('team-settings.edit');
    }
}
