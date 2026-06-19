<?php

namespace App\Services\Ai;

use RuntimeException;

class AssessmentGenerationException extends RuntimeException
{
    public static function invalidOutput(string $message): self
    {
        return new self("Invalid assessment generation output: {$message}");
    }

    public function toValidationMessage(): string
    {
        return __('The AI response could not be used. Please try again or edit the question manually.');
    }
}
