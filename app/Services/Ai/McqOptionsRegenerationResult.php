<?php

namespace App\Services\Ai;

readonly class McqOptionsRegenerationResult
{
    /**
     * @param  array<int, string>  $options
     * @param  array<int, string>  $correctAnswer
     */
    public function __construct(
        public array $options,
        public array $correctAnswer,
    ) {}
}
