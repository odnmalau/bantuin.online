<?php

namespace App\Services\Ai;

final class StructuredOutputLists
{
    /**
     * @param  class-string<AssessmentCriticException|ResumeScreeningException>  $exceptionClass
     * @return array<int, string>
     */
    public static function nonEmptyStringList(mixed $value, string $key, string $exceptionClass): array
    {
        if (! is_array($value)) {
            throw $exceptionClass::invalidOutput("{$key} must be an array.");
        }

        return array_values(array_map(
            fn (mixed $item): string => trim((string) $item),
            array_filter($value, fn (mixed $item): bool => is_string($item) && trim($item) !== ''),
        ));
    }
}
