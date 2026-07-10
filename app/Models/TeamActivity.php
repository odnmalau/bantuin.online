<?php

namespace App\Models;

use Database\Factories\TeamActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'team_id',
    'actor_id',
    'actor_context',
    'action',
    'subject_type',
    'subject_id',
    'before_state',
    'after_state',
    'reason',
    'occurred_at',
])]
class TeamActivity extends Model
{
    /** @use HasFactory<TeamActivityFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Team Activity is append-only.'));
        static::deleting(fn () => throw new LogicException('Team Activity is append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
