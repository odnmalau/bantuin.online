<?php

namespace App\Services\Ai;

use RuntimeException;

class AssessmentEvaluationException extends RuntimeException
{
    public static function invalidOutput(string $message): self
    {
        return new self("Invalid assessment evaluation output: {$message}");
    }
}
