<?php

return [
    'threshold' => (int) env('ASSESSMENT_PASSING_SCORE', 75),

    'qwen' => [
        'provider' => env('QWEN_PROVIDER', 'qwen'),
        'model' => env('QWEN_MODEL', 'qwen3.7-plus'),
        'timeout' => (int) env('QWEN_TIMEOUT', 30),
        'transport_attempt_count' => (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2),
        'transport_retry_sleep_ms' => (int) env('ASSESSMENT_QWEN_TRANSPORT_RETRY_SLEEP_MS', 300),
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

    'queue' => [
        /*
         * Supported evaluation calls: initial evaluator + configured repair attempts + critic.
         * attemptSeconds = qwen_timeout * transport_attempt_count
         * evaluation_timeout must exceed supported_calls * attemptSeconds + processing margin.
         * database/redis retry_after must exceed evaluation_timeout + safety margin.
         */
        'evaluation_processing_margin' => (int) env('ASSESSMENT_EVALUATION_PROCESSING_MARGIN', 30),
        'retry_after_safety_margin' => (int) env('ASSESSMENT_QUEUE_RETRY_AFTER_SAFETY_MARGIN', 60),
        'evaluation_timeout' => (int) env(
            'ASSESSMENT_EVALUATION_JOB_TIMEOUT',
            ((int) env('QWEN_TIMEOUT', 30)
                * (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2)
                * (1 + (int) env('ASSESSMENT_EVALUATION_REPAIR_ATTEMPTS', 1) + 1))
            + (int) env('ASSESSMENT_EVALUATION_PROCESSING_MARGIN', 30),
        ),
    ],

    'secure_exam' => [
        'require_fullscreen' => (bool) env('ASSESSMENT_EXAM_REQUIRE_FULLSCREEN', true),
        'max_integrity_warnings' => (int) env('ASSESSMENT_EXAM_MAX_INTEGRITY_WARNINGS', 3),
        'auto_submit_on_max_warnings' => (bool) env('ASSESSMENT_EXAM_AUTO_SUBMIT_ON_MAX_WARNINGS', true),
        'block_copy_paste' => (bool) env('ASSESSMENT_EXAM_BLOCK_COPY_PASTE', true),
        'enforce_section_timers' => (bool) env('ASSESSMENT_EXAM_ENFORCE_SECTION_TIMERS', true),
        'max_answer_characters' => (int) env('ASSESSMENT_EXAM_MAX_ANSWER_CHARACTERS', 20000),
    ],
];
