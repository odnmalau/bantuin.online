<?php

namespace App\Models;

use App\TeamMembershipRole;
use Database\Factories\TeamMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['team_id', 'user_id', 'role', 'started_at', 'ended_at', 'last_used_at'])]
class TeamMembership extends Model
{
    /** @use HasFactory<TeamMembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @param Builder<TeamMembership> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    protected static function booted(): void
    {
        static::updating(function (TeamMembership $membership): void {
            $wasEffectiveOwner = $membership->getRawOriginal('role') === TeamMembershipRole::Owner->value
                && $membership->getRawOriginal('ended_at') === null;

            if ($wasEffectiveOwner && $membership->isDirty(['team_id', 'user_id', 'role', 'ended_at'])) {
                throw new LogicException('The sole Owner membership cannot be changed or ended.');
            }
        });

        static::deleting(function (TeamMembership $membership): void {
            if ($membership->role === TeamMembershipRole::Owner && $membership->ended_at === null) {
                throw new LogicException('The sole Owner membership cannot be deleted.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => TeamMembershipRole::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
