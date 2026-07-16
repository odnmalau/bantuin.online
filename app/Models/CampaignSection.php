<?php

namespace App\Models;

use Database\Factories\CampaignSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'campaign_id',
    'title',
    'description',
    'duration_minutes',
    'weight',
    'sort_order',
])]
class CampaignSection extends Model
{
    /** @use HasFactory<CampaignSectionFactory> */
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
     * Get section questions.
     *
     * @return HasMany<CampaignQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CampaignQuestion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
