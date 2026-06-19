<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignExamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly CampaignInvitation $invitation,
        public readonly Campaign $campaign,
        public readonly string $inviteUrl,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You are invited to complete the :campaign assessment', [
                'campaign' => $this->campaign->title,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.campaign-exam-invitation',
            with: [
                'campaignTitle' => $this->campaign->title,
                'roleTitle' => $this->campaign->role_title,
                'inviteUrl' => $this->inviteUrl,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
