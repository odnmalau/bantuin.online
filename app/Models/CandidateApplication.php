<?php

namespace App\Models;

use Database\Factories\CandidateApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'campaign_invitation_id',
    'resume_path',
    'resume_original_name',
    'resume_uploaded_at',
    'locked_at',
])]
class CandidateApplication extends Model
{
    /** @use HasFactory<CandidateApplicationFactory> */
    use HasFactory;

    /** @return BelongsTo<CampaignInvitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(CampaignInvitation::class, 'campaign_invitation_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resume_uploaded_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
