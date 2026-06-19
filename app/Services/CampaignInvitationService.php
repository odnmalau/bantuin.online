<?php

namespace App\Services;

use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\Jobs\SendCampaignExamInvitationEmail;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use App\QuestionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $invitation = CampaignInvitation::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'email' => $normalizedEmail,
            ],
            [
                'user_id' => null,
                'token_hash' => hash('sha256', $plainToken),
                'invited_by' => $invitedBy->id,
                'sent_at' => null,
                'accepted_at' => null,
                'expires_at' => now()->addDays(14),
                'status' => CampaignInvitationStatus::Pending,
            ],
        );

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
        $this->ensureInvitationMatchesUser($invitation, $user);
        $this->refreshInvitationExpiry($invitation);

        if ($invitation->status === CampaignInvitationStatus::Accepted
            && $invitation->user_id === $user->id) {
            return $invitation;
        }

        if ($invitation->status !== CampaignInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation is no longer available.'),
            ]);
        }

        $invitation->update([
            'user_id' => $user->id,
            'accepted_at' => now(),
            'status' => CampaignInvitationStatus::Accepted,
        ]);

        return $invitation->fresh();
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

    private function refreshInvitationExpiry(CampaignInvitation $invitation): void
    {
        if ($invitation->status === CampaignInvitationStatus::Revoked) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has been revoked.'),
            ]);
        }

        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            if ($invitation->status === CampaignInvitationStatus::Pending) {
                $invitation->update([
                    'status' => CampaignInvitationStatus::Expired,
                ]);
            }

            throw ValidationException::withMessages([
                'invitation' => __('This invitation has expired.'),
            ]);
        }
    }
}
