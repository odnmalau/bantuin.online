<?php

namespace App\Services;

final readonly class ResumeTextExtractionResult
{
    public function __construct(
        public string $text,
        public int $originalCharacterCount,
        public int $retainedCharacterCount,
        public bool $wasTruncated,
    ) {}

    /**
     * Normalize malformed UTF-8 and bound text to the configured character budget.
     */
    public static function fromNormalizedText(string $text, ?int $maxCharacters = null): self
    {
        $maxCharacters = max(
            0,
            $maxCharacters ?? (int) config('assessment.resume.max_extracted_characters', 30000),
        );
        $normalizedText = mb_scrub($text, 'UTF-8');
        $originalCharacterCount = mb_strlen($normalizedText);
        $maxBytes = $maxCharacters * 4;
        $retainedText = mb_strcut($normalizedText, 0, $maxBytes, 'UTF-8');
        $retainedText = mb_substr($retainedText, 0, $maxCharacters);

        return new self(
            text: $retainedText,
            originalCharacterCount: $originalCharacterCount,
            retainedCharacterCount: mb_strlen($retainedText),
            wasTruncated: strlen($retainedText) < strlen($normalizedText),
        );
    }
}
