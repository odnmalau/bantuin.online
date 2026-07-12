<?php

namespace App\Models;

use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use Database\Factories\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'email',
    'role',
    'invited_by',
    'token_hash',
    'status',
    'expires_at',
    'accepted_at',
    'accepted_by',
    'revoked_at',
])]
class TeamInvitation extends Model
{
    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => TeamInvitationStatus::Pending,
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by')->withTrashed();
    }

    public function isRedeemable(): bool
    {
        return $this->status === TeamInvitationStatus::Pending
            && ! $this->expires_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => TeamMembershipRole::class,
            'status' => TeamInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
