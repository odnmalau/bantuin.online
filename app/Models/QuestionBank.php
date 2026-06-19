<?php

namespace App\Models;

use Database\Factories\QuestionBankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by',
    'title',
    'description',
    'skill_area',
    'difficulty',
    'is_active',
    'ai_generation_audit',
])]
class QuestionBank extends Model
{
    /** @use HasFactory<QuestionBankFactory> */
    use HasFactory;

    /**
     * Get the admin that created the bank.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get reusable questions in this bank.
     *
     * @return HasMany<BankQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(BankQuestion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ai_generation_audit' => 'array',
        ];
    }
}
