<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['name', 'email', 'google_id', 'avatar'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the user's assessments.
     *
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get campaigns created by this user.
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    /**
     * Get campaign exam invitations assigned to this candidate.
     *
     * @return HasMany<CampaignInvitation, $this>
     */
    public function campaignInvitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /** @return HasMany<TeamMembership, $this> */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /** @return HasMany<TeamMembership, $this> */
    public function activeTeamMemberships(): HasMany
    {
        return $this->teamMemberships()->whereNull('ended_at');
    }

    /** @return HasOne<TeamMembership, $this> */
    public function currentTeamMembership(): HasOne
    {
        return $this->hasOne(TeamMembership::class, 'user_id')
            ->whereColumn('team_memberships.team_id', 'users.current_team_id')
            ->whereNull('ended_at');
    }

    /** @return HasMany<PlatformOperatorAuthority, $this> */
    public function platformOperatorAuthorities(): HasMany
    {
        return $this->hasMany(PlatformOperatorAuthority::class);
    }

    public function isPlatformOperator(): bool
    {
        return $this->platformOperatorAuthorities()->active()->exists();
    }

    public function selectCurrentTeam(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            $membership = $this->activeTeamMemberships()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw new LogicException('Current Team requires an active Team Membership.');
            }

            $membership->update(['last_used_at' => now()]);
            $this->forceFill(['current_team_id' => $team->id])->save();
        });
    }

    public function replaceCurrentTeamAfterMembershipEnds(int $endedTeamId): void
    {
        if ($this->current_team_id !== $endedTeamId) {
            return;
        }

        $fallback = $this->activeTeamMemberships()
            ->where('team_id', '!=', $endedTeamId)
            ->whereHas('team')
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($fallback !== null) {
            $fallback->update(['last_used_at' => now()]);
        }

        $this->forceFill(['current_team_id' => $fallback?->team_id])->save();
    }
}
