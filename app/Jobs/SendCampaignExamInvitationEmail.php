<?php

namespace App\Jobs;

use App\Mail\CampaignExamInvitationMail;
use App\Models\CampaignInvitation;
use App\Services\CampaignInvitationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendCampaignExamInvitationEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $teamId;

    public ?string $deliveryClaim = null;

    public function __construct(
        public CampaignInvitation $invitation,
        public string $plainToken,
    ) {
        $this->teamId = (int) $invitation->campaign()->value('team_id');
        $this->deliveryClaim = (string) Str::uuid();
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignInvitationService $invitations): void
    {
        $deliveryClaim = $this->deliveryClaim ?? $this->legacyDeliveryClaim();
        $invitation = $invitations->claimEmailDelivery(
            $this->invitation->id,
            $this->plainToken,
            $deliveryClaim,
            $this->teamId,
        );

        if ($invitation === null) {
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
            $invitations->releaseEmailDelivery($invitation->id, $deliveryClaim);
            report($exception);
            Log::warning('Campaign exam invitation email failed.', [
                'invitation_id' => $invitation->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $invitations->completeEmailDelivery($invitation->id, $this->plainToken, $deliveryClaim);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    private function legacyDeliveryClaim(): string
    {
        $hash = hash('sha256', 'legacy:'.$this->plainToken);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
