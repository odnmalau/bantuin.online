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
     * Bound already-normalized résumé text to the configured character budget.
     */
    public static function fromNormalizedText(string $text, ?int $maxCharacters = null): self
    {
        $maxCharacters ??= max(0, (int) config('assessment.resume.max_extracted_characters', 30000));
        $originalCharacterCount = mb_strlen($text);

        if ($originalCharacterCount <= $maxCharacters) {
            return new self(
                text: $text,
                originalCharacterCount: $originalCharacterCount,
                retainedCharacterCount: $originalCharacterCount,
                wasTruncated: false,
            );
        }

        $retainedText = mb_substr($text, 0, $maxCharacters);

        return new self(
            text: $retainedText,
            originalCharacterCount: $originalCharacterCount,
            retainedCharacterCount: mb_strlen($retainedText),
            wasTruncated: true,
        );
    }
}
