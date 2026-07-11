<?php

namespace App\Models;

use App\OwnershipTransferStatus;
use Database\Factories\OwnershipTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'owner_membership_id',
    'recipient_membership_id',
    'token_hash',
    'status',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
class OwnershipTransfer extends Model
{
    /** @use HasFactory<OwnershipTransferFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => OwnershipTransferStatus::Pending,
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TeamMembership, $this> */
    public function ownerMembership(): BelongsTo
    {
        return $this->belongsTo(TeamMembership::class, 'owner_membership_id');
    }

    /** @return BelongsTo<TeamMembership, $this> */
    public function recipientMembership(): BelongsTo
    {
        return $this->belongsTo(TeamMembership::class, 'recipient_membership_id');
    }

    public function isRedeemable(): bool
    {
        return $this->status === OwnershipTransferStatus::Pending && ! $this->expires_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OwnershipTransferStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
