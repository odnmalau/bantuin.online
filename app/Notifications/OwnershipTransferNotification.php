<?php

namespace App\Notifications;

use App\Models\OwnershipTransfer;
use App\OwnershipTransferStatus;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnershipTransferNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public OwnershipTransfer $transfer,
        private string $plainToken,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Ownership Transfer for :team', ['team' => $this->transfer->team->name]))
            ->greeting(__('You have been offered ownership of :team.', ['team' => $this->transfer->team->name]))
            ->line(__('The current Owner remains responsible until you accept.'))
            ->line(__('This offer expires on :date.', ['date' => $this->transfer->expires_at->toFormattedDateString()]))
            ->action(__('Review Ownership Transfer'), route('ownership-transfers.show', $this->plainToken));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $transfer = OwnershipTransfer::query()
            ->with(['team', 'ownerMembership', 'recipientMembership'])
            ->find($this->transfer->id);

        return $transfer !== null
            && $transfer->status === OwnershipTransferStatus::Pending
            && ! $transfer->expires_at->isPast()
            && hash_equals($transfer->token_hash, hash('sha256', $this->plainToken))
            && $transfer->team->status === TeamStatus::Active
            && $transfer->ownerMembership->isActive()
            && $transfer->ownerMembership->role === TeamMembershipRole::Owner
            && $transfer->recipientMembership->isActive()
            && $transfer->recipientMembership->user_id === $notifiable->getKey();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
