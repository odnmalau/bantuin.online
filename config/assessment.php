<?php

return [
    'threshold' => (int) env('ASSESSMENT_PASSING_SCORE', 75),

    'qwen' => [
        'provider' => env('QWEN_PROVIDER', 'qwen'),
        'model' => env('QWEN_MODEL', 'qwen3.7-plus'),
        'timeout' => (int) env('QWEN_TIMEOUT', 30),
    ],

    'generator' => [
        'prompt_version' => (string) env('ASSESSMENT_GENERATOR_PROMPT_VERSION', '1'),
    ],

    'evaluation' => [
        'repair_attempts' => (int) env('ASSESSMENT_EVALUATION_REPAIR_ATTEMPTS', 1),
    ],

    'resume' => [
        'max_kilobytes' => (int) env('ASSESSMENT_RESUME_MAX_KB', 5120),
        'pdftotext_bin' => env('ASSESSMENT_PDFTOTEXT_BIN'),
    ],

    'ranking' => [
        'weights' => [
            'resume_score' => (int) env('ASSESSMENT_RANKING_RESUME_WEIGHT', 35),
            'essay_score' => (int) env('ASSESSMENT_RANKING_ESSAY_WEIGHT', 50),
            'mcq_score' => (int) env('ASSESSMENT_RANKING_MCQ_WEIGHT', 15),
        ],
    ],

    'secure_exam' => [
        'require_fullscreen' => (bool) env('ASSESSMENT_EXAM_REQUIRE_FULLSCREEN', true),
        'max_integrity_warnings' => (int) env('ASSESSMENT_EXAM_MAX_INTEGRITY_WARNINGS', 3),
        'auto_submit_on_max_warnings' => (bool) env('ASSESSMENT_EXAM_AUTO_SUBMIT_ON_MAX_WARNINGS', true),
        'block_copy_paste' => (bool) env('ASSESSMENT_EXAM_BLOCK_COPY_PASTE', true),
        'enforce_section_timers' => (bool) env('ASSESSMENT_EXAM_ENFORCE_SECTION_TIMERS', true),
    ],
];
