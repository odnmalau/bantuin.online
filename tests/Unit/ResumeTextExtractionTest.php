<?php

use App\Services\ResumeTextExtractionResult;

beforeEach(function () {
    config()->set('assessment.resume.max_extracted_characters', 30);
});

test('resume text below the character budget is retained in full', function () {
    $result = ResumeTextExtractionResult::fromNormalizedText('short resume text');

    expect($result)
        ->text->toBe('short resume text')
        ->originalCharacterCount->toBe(17)
        ->retainedCharacterCount->toBe(17)
        ->wasTruncated->toBeFalse();
});

test('resume text at the exact character budget is not truncated', function () {
    $text = str_repeat('a', 30);

    $result = ResumeTextExtractionResult::fromNormalizedText($text);

    expect($result)
        ->text->toBe($text)
        ->originalCharacterCount->toBe(30)
        ->retainedCharacterCount->toBe(30)
        ->wasTruncated->toBeFalse();
});

test('resume text over the character budget is truncated without retaining discarded content', function () {
    $text = str_repeat('a', 30).'DISCARDED_TAIL';

    $result = ResumeTextExtractionResult::fromNormalizedText($text);

    expect($result)
        ->text->toBe(str_repeat('a', 30))
        ->originalCharacterCount->toBe(44)
        ->retainedCharacterCount->toBe(30)
        ->wasTruncated->toBeTrue()
        ->and($result->text)->not->toContain('DISCARDED_TAIL');
});

test('resume text truncation is multibyte safe', function () {
    config()->set('assessment.resume.max_extracted_characters', 5);

    $result = ResumeTextExtractionResult::fromNormalizedText('日本語テキスト');

    expect($result)
        ->text->toBe('日本語テキ')
        ->originalCharacterCount->toBe(7)
        ->retainedCharacterCount->toBe(5)
        ->wasTruncated->toBeTrue()
        ->and(strlen($result->text))->toBeLessThanOrEqual(20);
});

test('resume text normalizes malformed UTF-8 before applying its bounds', function () {
    $result = ResumeTextExtractionResult::fromNormalizedText("valid\xC3\x28tail");

    expect(mb_check_encoding($result->text, 'UTF-8'))->toBeTrue()
        ->and($result->text)->toBe('valid?(tail')
        ->and($result->originalCharacterCount)->toBe(11)
        ->and($result->retainedCharacterCount)->toBe(11)
        ->and($result->wasTruncated)->toBeFalse();
});
