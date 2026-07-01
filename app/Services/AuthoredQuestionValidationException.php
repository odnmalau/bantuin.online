<?php

namespace App\Services;

use RuntimeException;

class AuthoredQuestionValidationException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The authored question is invalid.');
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
