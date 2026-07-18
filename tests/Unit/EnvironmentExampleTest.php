<?php

use Dotenv\Dotenv;

test('environment example documents assessment configuration', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));
    $assessmentConfiguration = file_get_contents(config_path('assessment.php'));

    expect($environmentExample)->not->toBeFalse()
        ->and($assessmentConfiguration)->not->toBeFalse();

    Dotenv::parse($environmentExample);

    preg_match_all("/env\\(\\s*'([A-Z][A-Z0-9_]*)'/", $assessmentConfiguration, $configuredMatches);
    preg_match_all('/^\s*#?\s*([A-Z][A-Z0-9_]*)=/m', $environmentExample, $documentedMatches);

    $undocumentedVariables = array_values(array_diff(
        array_unique($configuredMatches[1]),
        array_unique($documentedMatches[1]),
    ));

    expect($undocumentedVariables)->toBe([]);
});

test('environment example exposes one qwen API key variable', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));
    $aiConfiguration = file_get_contents(config_path('ai.php'));

    expect($environmentExample)->not->toBeFalse()
        ->and($aiConfiguration)->not->toBeFalse()
        ->and($environmentExample)->toMatch('/^QWEN_API_KEY=/m')
        ->not->toContain('DASHSCOPE_API_KEY')
        ->not->toContain('ASSESSMENT_RANKING_RESUME_WEIGHT')
        ->not->toContain('ASSESSMENT_RANKING_ASSESSMENT_WEIGHT')
        ->and($aiConfiguration)->toContain("env('QWEN_API_KEY')")
        ->not->toContain('DASHSCOPE_API_KEY');
});

test('environment example documents the optional PostgreSQL integration database', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->not->toBeFalse()
        ->and($environmentExample)->toContain('# POSTGRES_INTEGRATION_DATABASE=bantuin_integration');
});
