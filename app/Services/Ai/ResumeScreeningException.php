<?php

namespace App\Services\Ai;

use RuntimeException;

class ResumeScreeningException extends RuntimeException
{
    public static function invalidOutput(string $message): self
    {
        return new self("Invalid resume screening output: {$message}");
    }
}
