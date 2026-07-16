<?php

namespace App\Models;

use App\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'campaign_id',
    'answers_payload',
    'resume_path',
    'resume_original_name',
    'resume_text',
    'resume_score',
    'resume_justification',
    'resume_payload',
    'assessment_score',
    'evaluation_payload',
    'ranking_score',
    'ranking_payload',
    'critic_payload',
    'needs_manual_review',
    'ai_justification',
    'ai_email_subject',
    'ai_email_body',
    'approved_email_subject',
    'approved_email_body',
    'status',
    'evaluated_at',
    'approved_by',
    'approved_at',
    'rejected_at',
    'email_sent_at',
    'resume_screening_attempt_id',
    'resume_screening_started_at',
    'evaluation_attempt_id',
    'evaluation_started_at',
    'email_delivery_attempt_id',
    'email_delivery_started_at',
])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers_payload' => 'array',
            'resume_score' => 'integer',
            'resume_payload' => 'array',
            'assessment_score' => 'integer',
            'evaluation_payload' => 'array',
            'ranking_score' => 'integer',
            'ranking_payload' => 'array',
            'critic_payload' => 'array',
            'needs_manual_review' => 'boolean',
            'status' => AssessmentStatus::class,
            'evaluated_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'resume_screening_started_at' => 'datetime',
            'evaluation_started_at' => 'datetime',
            'email_delivery_started_at' => 'datetime',
        ];
    }

    /**
     * Get the candidate that owns the assessment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the campaign this assessment belongs to.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the admin that approved the assessment.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * Get timeline events for this assessment.
     *
     * @return HasMany<AssessmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssessmentEvent::class);
    }

    public function resetEvaluationForRetry(): void
    {
        $this->update([
            'ai_justification' => null,
            'ai_email_subject' => null,
            'ai_email_body' => null,
            'assessment_score' => null,
            'evaluation_payload' => null,
            'ranking_score' => null,
            'ranking_payload' => null,
            'critic_payload' => null,
            'evaluated_at' => null,
            'evaluation_attempt_id' => null,
            'evaluation_started_at' => null,
            'status' => AssessmentStatus::Submitted,
        ]);
    }
}
