<?php

namespace App\Models;

use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use Database\Factories\CampaignQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_id',
    'campaign_section_id',
    'type',
    'grading_mode',
    'prompt',
    'options',
    'correct_answer',
    'expected_rubric',
    'points',
    'difficulty',
    'skill_tags',
    'ai_generated',
    'status',
    'is_required',
    'sort_order',
])]
class CampaignQuestion extends Model
{
    /** @use HasFactory<CampaignQuestionFactory> */
    use HasFactory;

    /**
     * Get the owning campaign.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the owning section.
     *
     * @return BelongsTo<CampaignSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CampaignSection::class, 'campaign_section_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'grading_mode' => QuestionGradingMode::class,
            'options' => 'array',
            'correct_answer' => 'array',
            'points' => 'integer',
            'skill_tags' => 'array',
            'ai_generated' => 'boolean',
            'status' => QuestionStatus::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
