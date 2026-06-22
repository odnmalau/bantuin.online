<?php

namespace App\Models;

use App\ExamSessionStatus;
use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'campaign_id',
    'assessment_id',
    'status',
    'current_section_id',
    'current_section_started_at',
    'current_section_expires_at',
    'completed_section_ids',
    'warning_count',
    'integrity_events',
    'answer_drafts',
    'resume_path',
    'resume_original_name',
    'submission_reason',
    'finalized_at',
])]
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExamSessionStatus::class,
            'current_section_started_at' => 'datetime',
            'current_section_expires_at' => 'datetime',
            'completed_section_ids' => 'array',
            'integrity_events' => 'array',
            'answer_drafts' => 'array',
            'finalized_at' => 'datetime',
            'warning_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<CampaignSection, $this>
     */
    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(CampaignSection::class, 'current_section_id');
    }

    public function isActive(): bool
    {
        return $this->status === ExamSessionStatus::InProgress;
    }
}
