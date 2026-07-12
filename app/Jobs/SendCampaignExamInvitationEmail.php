<?php

namespace App\Jobs;

use App\CampaignInvitationStatus;
use App\Mail\CampaignExamInvitationMail;
use App\Models\CampaignInvitation;
use App\Services\CampaignInvitationService;
use App\TeamStatus;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCampaignExamInvitationEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $teamId;

    public function __construct(
        public CampaignInvitation $invitation,
        public string $plainToken,
    ) {
        $this->teamId = (int) $invitation->campaign()->value('team_id');
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignInvitationService $invitations): void
    {
        $invitation = $this->invitation->fresh(['campaign.team']);

        if ($invitation === null || $invitation->campaign === null) {
            return;
        }

        if ($invitation->campaign->team_id !== $this->teamId || $invitation->campaign->team?->status !== TeamStatus::Active) {
            Log::warning('Campaign exam invitation email skipped because its Team is no longer writable.', [
                'invitation_id' => $invitation->id,
                'team_id' => $this->teamId,
            ]);

            return;
        }

        if ($invitation->status !== CampaignInvitationStatus::Pending) {
            Log::warning('Campaign exam invitation email skipped because invitation is not pending.', [
                'invitation_id' => $invitation->id,
                'status' => $invitation->status->value,
            ]);

            return;
        }

        if (! hash_equals($invitation->token_hash, hash('sha256', $this->plainToken))) {
            Log::warning('Campaign exam invitation email skipped because its token is stale.', [
                'invitation_id' => $invitation->id,
                'team_id' => $this->teamId,
            ]);

            return;
        }

        $inviteUrl = $invitations->inviteUrlForToken($this->plainToken);

        try {
            Mail::to($invitation->email)->send(new CampaignExamInvitationMail(
                invitation: $invitation,
                campaign: $invitation->campaign,
                inviteUrl: $inviteUrl,
            ));
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Campaign exam invitation email failed.', [
                'invitation_id' => $invitation->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $invitation->update([
            'sent_at' => now(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }
}
