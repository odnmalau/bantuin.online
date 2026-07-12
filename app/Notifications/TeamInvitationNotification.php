<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
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
            ->subject(__('Invitation to join :team', ['team' => $this->invitation->team->name]))
            ->greeting(__('You are invited to join :team.', ['team' => $this->invitation->team->name]))
            ->line(__('The offered Team role is :role.', ['role' => $this->invitation->role->value]))
            ->line(__('This invitation expires on :date.', ['date' => $this->invitation->expires_at->toFormattedDateString()]))
            ->action(__('Review Team Invitation'), route('team-invitations.show', $this->plainToken));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invitation = TeamInvitation::query()->with('team')->find($this->invitation->id);

        if ($invitation === null
            || $invitation->status !== TeamInvitationStatus::Pending
            || $invitation->expires_at->isPast()
            || ! hash_equals($invitation->token_hash, hash('sha256', $this->plainToken))
            || $invitation->team->status !== TeamStatus::Active) {
            return false;
        }

        $inviterRole = TeamMembership::query()
            ->active()
            ->where('team_id', $invitation->team_id)
            ->where('user_id', $invitation->invited_by)
            ->first()
            ?->role;

        if ($invitation->actor_context === 'platform_operator') {
            return User::query()->find($invitation->invited_by)?->isPlatformOperator() === true;
        }

        return $inviterRole === TeamMembershipRole::Owner
            || ($inviterRole === TeamMembershipRole::Administrator
                && $invitation->role === TeamMembershipRole::Collaborator);
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
