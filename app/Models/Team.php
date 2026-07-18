<?php

namespace App\Models;

use App\TeamMembershipRole;
use App\TeamStatus;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['name', 'status', 'deactivated_at', 'deactivated_by', 'owner_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TeamStatus::Active,
    ];

    private ?int $pendingOwnerId = null;

    private ?bool $demo = null;

    public static function createForOwner(User $owner, string $name): self
    {
        return DB::transaction(fn (): self => self::query()->create([
            'name' => $name,
            'owner_id' => $owner->id,
        ]));
    }

    public function setOwnerIdAttribute(int $ownerId): void
    {
        $this->pendingOwnerId = $ownerId;
    }

    /** @return HasMany<TeamMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /** @return HasMany<TeamMembership, $this> */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->whereNull('ended_at');
    }

    /** @return HasOne<TeamMembership, $this> */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(TeamMembership::class)
            ->where('role', TeamMembershipRole::Owner)
            ->whereNull('ended_at');
    }

    public function isDemo(): bool
    {
        return $this->demo ??= $this->ownerMembership()
            ->whereHas('user', fn (Builder $query): Builder => $query
                ->where('email', User::DEMO_ADMIN_EMAIL))
            ->exists();
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** @return HasMany<TeamActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(TeamActivity::class);
    }

    /** @return HasMany<TeamInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @return HasMany<OwnershipTransfer, $this> */
    public function ownershipTransfers(): HasMany
    {
        return $this->hasMany(OwnershipTransfer::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Team $team): void {
            if ($team->pendingOwnerId === null) {
                throw new LogicException('A Team must be created with exactly one Owner.');
            }
        });

        static::created(function (Team $team): void {
            TeamMembership::query()->create([
                'team_id' => $team->id,
                'user_id' => $team->pendingOwnerId,
                'role' => TeamMembershipRole::Owner,
                'started_at' => now(),
            ]);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TeamStatus::class,
            'deactivated_at' => 'datetime',
        ];
    }
}
