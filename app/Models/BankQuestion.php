<?php

namespace App\Models;

use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use Database\Factories\BankQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'question_bank_id',
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
    'sort_order',
])]
class BankQuestion extends Model
{
    /** @use HasFactory<BankQuestionFactory> */
    use HasFactory;

    /**
     * Get the question bank.
     *
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    /**
     * Get campaign snapshots copied from this bank question.
     *
     * @return HasMany<CampaignQuestion, $this>
     */
    public function campaignQuestions(): HasMany
    {
        return $this->hasMany(CampaignQuestion::class, 'source_bank_question_id');
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
            'sort_order' => 'integer',
        ];
    }
}
