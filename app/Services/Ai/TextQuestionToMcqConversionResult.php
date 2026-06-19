<?php

namespace App\Services\Ai;

readonly class TextQuestionToMcqConversionResult
{
    /**
     * @param  array<int, string>  $options
     * @param  array<int, string>  $correctAnswer
     */
    public function __construct(
        public string $prompt,
        public array $options,
        public array $correctAnswer,
    ) {}
}
