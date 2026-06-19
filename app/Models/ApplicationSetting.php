<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class ApplicationSetting extends Model
{
    public const AssessmentPassingScore = 'assessment.passing_score';

    public static function integer(string $key, int $default): int
    {
        $value = self::query()->where('key', $key)->value('value');

        if ($value === null || ! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function setInteger(string $key, int $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );
    }
}
