<?php

namespace App\Models;

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
    'prompt',
    'expected_rubric',
    'points',
    'difficulty',
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
            'points' => 'integer',
            'ai_generated' => 'boolean',
            'status' => QuestionStatus::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
