<?php

namespace App\Services\Ai;

use RuntimeException;

class AssessmentCriticException extends RuntimeException
{
    public static function invalidOutput(string $message): self
    {
        return new self("Invalid assessment critic output: {$message}");
    }
}
