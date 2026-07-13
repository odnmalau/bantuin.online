<?php

namespace App\Services;

use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\Jobs\SendCampaignExamInvitationEmail;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\QuestionStatus;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignInvitationService
{
    public const SESSION_PENDING_ID = 'campaign_invitation.pending_id';

    /**
     * @return array{invitation: CampaignInvitation, invite_url: string, plain_token: string}
     */
    public function create(
        Campaign $campaign,
        string $email,
        User $invitedBy,
        bool $sendEmail = true,
    ): array {
        $normalizedEmail = strtolower(trim($email));
        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($campaign, $normalizedEmail, $plainToken, $invitedBy): CampaignInvitation {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            Team::query()->whereKey($lockedCampaign->team_id)->lockForUpdate()->firstOrFail();

            $existingUserId = User::query()
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->value('id');
            $hasMembershipHistory = TeamMembership::query()
                ->where('team_id', $lockedCampaign->team_id)
                ->where(function ($query) use ($normalizedEmail, $existingUserId): void {
                    $query->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(email) = ?', [$normalizedEmail]));

                    if ($existingUserId !== null) {
                        $query->orWhere('user_id', $existingUserId);
                    }
                })
                ->exists();

            if ($hasMembershipHistory) {
                throw ValidationException::withMessages([
                    'email' => __('Team Membership history prevents candidacy in this Team.'),
                ]);
            }

            $existingInvitation = CampaignInvitation::query()
                ->where('campaign_id', $lockedCampaign->id)
                ->where(function ($query) use ($normalizedEmail, $existingUserId): void {
                    $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]);

                    if ($existingUserId !== null) {
                        $query->orWhere('user_id', $existingUserId);
                    }
                })
                ->lockForUpdate()
                ->first();

            if ($existingInvitation?->status === CampaignInvitationStatus::Accepted) {
                throw ValidationException::withMessages([
                    'email' => __('This person already has Candidate history for this Campaign.'),
                ]);
            }

            if ($existingInvitation?->send_claim !== null) {
                throw ValidationException::withMessages([
                    'email' => __('This Campaign Invitation is currently being sent. Try again shortly.'),
                ]);
            }

            $attributes = [
                'email' => $normalizedEmail,
                'user_id' => null,
                'token_hash' => hash('sha256', $plainToken),
                'invited_by' => $invitedBy->id,
                'sent_at' => null,
                'accepted_at' => null,
                'expires_at' => now()->addDays(14),
                'status' => CampaignInvitationStatus::Pending,
            ];

            if ($existingInvitation !== null) {
                $existingInvitation->update($attributes);

                return $existingInvitation;
            }

            return CampaignInvitation::query()->create([
                ...$attributes,
                'campaign_id' => $lockedCampaign->id,
            ]);
        });

        $invitation = $invitation->fresh();

        $inviteUrl = $this->inviteUrlForToken($plainToken);

        if ($sendEmail) {
            SendCampaignExamInvitationEmail::dispatch($invitation, $plainToken);
        }

        return [
            'invitation' => $invitation,
            'invite_url' => $inviteUrl,
            'plain_token' => $plainToken,
        ];
    }

    public function revoke(CampaignInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $lockedInvitation = CampaignInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvitation->status !== CampaignInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => __('Only pending Campaign Invitations may be revoked.'),
                ]);
            }

            if ($lockedInvitation->send_claim !== null) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Campaign Invitation is currently being sent. Try again shortly.'),
                ]);
            }

            $lockedInvitation->update([
                'status' => CampaignInvitationStatus::Revoked,
            ]);
        });
    }

    public function resend(CampaignInvitation $invitation): void
    {
        $plainToken = Str::random(64);

        $lockedInvitation = DB::transaction(function () use ($invitation, $plainToken): CampaignInvitation {
            $lockedInvitation = CampaignInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedInvitation->status, [CampaignInvitationStatus::Pending, CampaignInvitationStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Campaign Invitation cannot be resent.'),
                ]);
            }

            if ($lockedInvitation->send_claim !== null) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Campaign Invitation is currently being sent. Try again shortly.'),
                ]);
            }

            $lockedInvitation->update([
                'token_hash' => hash('sha256', $plainToken),
                'status' => CampaignInvitationStatus::Pending,
                'expires_at' => now()->addDays(14),
                'sent_at' => null,
            ]);

            return $lockedInvitation;
        });

        SendCampaignExamInvitationEmail::dispatch($lockedInvitation, $plainToken);
    }

    public function claimEmailDelivery(
        int $invitationId,
        string $plainToken,
        string $deliveryClaim,
        int $teamId,
    ): ?CampaignInvitation {
        $tokenHash = hash('sha256', $plainToken);
        $claimed = CampaignInvitation::query()
            ->whereKey($invitationId)
            ->where('status', CampaignInvitationStatus::Pending)
            ->where('token_hash', $tokenHash)
            ->whereNull('sent_at')
            ->where(function ($query) use ($deliveryClaim): void {
                $query->whereNull('send_claim')->orWhere('send_claim', $deliveryClaim);
            })
            ->whereHas('campaign', fn ($query) => $query
                ->where('team_id', $teamId)
                ->whereHas('team', fn ($teamQuery) => $teamQuery->where('status', TeamStatus::Active)))
            ->update([
                'send_claim' => $deliveryClaim,
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return CampaignInvitation::query()
            ->with('campaign.team')
            ->find($invitationId);
    }

    public function completeEmailDelivery(
        int $invitationId,
        string $plainToken,
        string $deliveryClaim,
    ): bool {
        $completed = CampaignInvitation::query()
            ->whereKey($invitationId)
            ->where('status', CampaignInvitationStatus::Pending)
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('send_claim', $deliveryClaim)
            ->update([
                'sent_at' => now(),
                'send_claim' => null,
            ]) === 1;

        if (! $completed) {
            $this->releaseEmailDelivery($invitationId, $deliveryClaim);
        }

        return $completed;
    }

    public function releaseEmailDelivery(int $invitationId, string $deliveryClaim): void
    {
        CampaignInvitation::query()
            ->whereKey($invitationId)
            ->where('send_claim', $deliveryClaim)
            ->update([
                'send_claim' => null,
            ]);
    }

    public function inviteUrlForToken(string $plainToken): string
    {
        return URL::route('invites.show', ['token' => $plainToken]);
    }

    public function findByPlainToken(string $plainToken): ?CampaignInvitation
    {
        return CampaignInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }

    public function rememberPendingInvitation(Request $request, CampaignInvitation $invitation): void
    {
        $request->session()->put(self::SESSION_PENDING_ID, $invitation->id);
    }

    public function forgetPendingInvitation(Request $request): void
    {
        $request->session()->forget(self::SESSION_PENDING_ID);
    }

    public function pendingInvitation(Request $request): ?CampaignInvitation
    {
        $invitationId = $request->session()->get(self::SESSION_PENDING_ID);

        if (! is_int($invitationId) && ! is_string($invitationId)) {
            return null;
        }

        return CampaignInvitation::query()->find((int) $invitationId);
    }

    public function acceptForUser(CampaignInvitation $invitation, User $user): CampaignInvitation
    {
        $accepted = DB::transaction(function () use ($invitation, $user): ?CampaignInvitation {
            $campaignId = CampaignInvitation::query()->whereKey($invitation->id)->value('campaign_id');
            $campaign = Campaign::query()->whereKey($campaignId)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($campaign->team_id)->lockForUpdate()->firstOrFail();
            $lockedInvitation = CampaignInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvitation->campaign_id !== $campaign->id) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is no longer available.'),
                ]);
            }

            $this->ensureInvitationMatchesUser($lockedInvitation, $user);

            if ($lockedInvitation->status === CampaignInvitationStatus::Pending
                && $lockedInvitation->expires_at?->isPast()) {
                $lockedInvitation->update(['status' => CampaignInvitationStatus::Expired]);

                return null;
            }

            if ($team->status !== TeamStatus::Active) {
                throw ValidationException::withMessages([
                    'invitation' => __('This Team is not accepting Candidate Invitations.'),
                ]);
            }

            if (TeamMembership::query()->whereBelongsTo($team)->whereBelongsTo($user)->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => __('Team Membership history prevents candidacy in this Team.'),
                ]);
            }

            if ($lockedInvitation->status !== CampaignInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is no longer available.'),
                ]);
            }

            $lockedInvitation->update([
                'user_id' => $user->id,
                'accepted_at' => now(),
                'status' => CampaignInvitationStatus::Accepted,
            ]);

            return $lockedInvitation->fresh();
        });

        if ($accepted === null) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has expired.'),
            ]);
        }

        return $accepted;
    }

    public function completePendingRedemption(Request $request, User $user): ?RedirectResponse
    {
        $invitation = $this->pendingInvitation($request);

        if ($invitation === null) {
            return null;
        }

        $this->forgetPendingInvitation($request);

        try {
            $invitation = $this->acceptForUser($invitation, $user);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->with('status', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('candidate.campaigns.exam', $invitation->campaign_id);
    }

    public function userCanAccessCampaignExam(User $user, Campaign $campaign): bool
    {
        if ($campaign->status !== CampaignStatus::Active) {
            return false;
        }

        if ($campaign->team()->where('status', TeamStatus::Active)->doesntExist()) {
            return false;
        }

        if (! $campaign->questions()->where('status', QuestionStatus::Approved->value)->exists()) {
            return false;
        }

        return CampaignInvitation::query()
            ->acceptedForUser($user)
            ->where('campaign_id', $campaign->id)
            ->exists();
    }

    /**
     * @return Collection<int, Campaign>
     */
    public function accessibleCampaignsForUser(User $user): Collection
    {
        return Campaign::query()
            ->where('status', CampaignStatus::Active->value)
            ->whereHas('team', fn ($query) => $query->where('status', TeamStatus::Active->value))
            ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Approved->value))
            ->whereHas('invitations', fn ($query) => $query->acceptedForUser($user))
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function invitationPayload(CampaignInvitation $invitation, ?string $inviteUrl = null): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'status' => $invitation->status->value,
            'status_label' => $invitation->status->label(),
            'sent_at' => $invitation->sent_at,
            'accepted_at' => $invitation->accepted_at,
            'expires_at' => $invitation->expires_at,
            'invite_url' => $inviteUrl,
            'can_revoke' => $invitation->status === CampaignInvitationStatus::Pending,
            'can_resend' => in_array($invitation->status, [
                CampaignInvitationStatus::Pending,
                CampaignInvitationStatus::Expired,
            ], true),
        ];
    }

    private function ensureInvitationMatchesUser(CampaignInvitation $invitation, User $user): void
    {
        if (! $invitation->matchesEmail($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation was sent to a different email address.'),
            ]);
        }
    }
}
