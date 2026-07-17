<?php

namespace App\Models;

use App\CampaignInvitationStatus;
use Database\Factories\CampaignInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'campaign_id',
    'email',
    'user_id',
    'token_hash',
    'invited_by',
    'sent_at',
    'accepted_at',
    'expires_at',
    'status',
    'send_claim',
])]
class CampaignInvitation extends Model
{
    /** @use HasFactory<CampaignInvitationFactory> */
    use HasFactory;

    /**
     * @param  Builder<CampaignInvitation>  $query
     */
    public function scopeAcceptedForUser(Builder $query, User $user): void
    {
        $query
            ->where('status', CampaignInvitationStatus::Accepted)
            ->where('user_id', $user->id);
    }

    /**
     * Get the campaign this invitation belongs to.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the invited candidate user once they accept.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the admin who created the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    /** @return HasOne<CandidateApplication, $this> */
    public function application(): HasOne
    {
        return $this->hasOne(CandidateApplication::class);
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== CampaignInvitationStatus::Pending) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function matchesEmail(string $email): bool
    {
        return strcasecmp($this->email, $email) === 0;
    }

    /**
     * @return array{invitation: self, plain_token: string}
     */
    public static function issueToken(self $invitation): array
    {
        $plainToken = Str::random(64);

        $invitation->forceFill([
            'token_hash' => hash('sha256', $plainToken),
            'sent_at' => null,
            'send_claim' => null,
        ])->save();

        return [
            'invitation' => $invitation,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignInvitationStatus::class,
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
