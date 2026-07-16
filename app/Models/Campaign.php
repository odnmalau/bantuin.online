<?php

namespace App\Models;

use App\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by',
    'team_id',
    'title',
    'role_title',
    'seniority',
    'job_description',
    'required_skills',
    'language',
    'threshold_score',
    'ranking_weights',
    'status',
    'ai_generation_audit',
    'activated_at',
])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Campaign $campaign): void {
            if ($campaign->isDirty('team_id')) {
                throw new \LogicException('Campaign Team ownership is immutable.');
            }
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the admin that created the campaign.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Get the campaign sections.
     *
     * @return HasMany<CampaignSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(CampaignSection::class);
    }

    /**
     * Get the campaign questions.
     *
     * @return HasMany<CampaignQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CampaignQuestion::class);
    }

    /**
     * Get assessments submitted for the campaign.
     *
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get exam invitations for this campaign.
     *
     * @return HasMany<CampaignInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    /**
     * Get exam sessions for this campaign.
     *
     * @return HasMany<ExamSession, $this>
     */
    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * @return array{resume_score: int, assessment_score: int}
     */
    public static function defaultRankingWeights(): array
    {
        return self::normalizeRankingWeights(config('assessment.ranking.weights', []));
    }

    /**
     * @return array{resume_score: int, assessment_score: int}
     */
    public function resolvedRankingWeights(): array
    {
        return self::defaultRankingWeights();
    }

    public function hasConfiguredRankingWeights(): bool
    {
        if (! is_array($this->ranking_weights)) {
            return false;
        }

        return self::rankingWeightsTotal($this->ranking_weights) === 100;
    }

    /**
     * @param  array<string, mixed>  $weights
     */
    public static function rankingWeightsTotal(array $weights): int
    {
        return max(0, (int) ($weights['resume_score'] ?? 0))
            + max(0, (int) ($weights['assessment_score'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array{resume_score: int, assessment_score: int}
     */
    public static function normalizeRankingWeights(array $weights): array
    {
        return [
            'resume_score' => max(0, (int) ($weights['resume_score'] ?? 0)),
            'assessment_score' => max(0, (int) ($weights['assessment_score'] ?? 100)),
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
            'required_skills' => 'array',
            'threshold_score' => 'integer',
            'ranking_weights' => 'array',
            'ai_generation_audit' => 'array',
            'status' => CampaignStatus::class,
            'activated_at' => 'datetime',
        ];
    }
}
