<?php

return [
    'threshold' => (int) env('ASSESSMENT_PASSING_SCORE', 75),

    'qwen' => [
        'provider' => env('QWEN_PROVIDER', 'qwen'),
        'model' => env('QWEN_MODEL', 'qwen3.7-plus'),
        'reasoner_model' => env('QWEN_REASONER_MODEL', 'qwen3.7-max'),
        'structured_model' => env('QWEN_STRUCTURED_MODEL', env('QWEN_MODEL', 'qwen3.7-plus')),
        'timeout' => (int) env('QWEN_TIMEOUT', 30),
        'reasoner_timeout' => (int) env('QWEN_REASONER_TIMEOUT', 120),
        'transport_attempt_count' => (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2),
        'transport_retry_sleep_ms' => (int) env('ASSESSMENT_QWEN_TRANSPORT_RETRY_SLEEP_MS', 300),
    ],

    'generator' => [
        'prompt_version' => (string) env('ASSESSMENT_GENERATOR_PROMPT_VERSION', '2'),
    ],

    'evaluation' => [
        'repair_attempts' => (int) env('ASSESSMENT_EVALUATION_REPAIR_ATTEMPTS', 1),
        'minimum_confidence' => (int) env('ASSESSMENT_EVALUATION_MINIMUM_CONFIDENCE', 70),
        'manual_review_margin' => (int) env('ASSESSMENT_EVALUATION_REVIEW_MARGIN', 3),
    ],

    'resume' => [
        'max_kilobytes' => (int) env('ASSESSMENT_RESUME_MAX_KB', 5120),
        'max_extracted_characters' => (int) env('ASSESSMENT_RESUME_MAX_EXTRACTED_CHARACTERS', 30000),
        'pdftotext_bin' => env('ASSESSMENT_PDFTOTEXT_BIN'),
    ],

    'ranking' => [
        'weights' => [
            'resume_score' => 0,
            'assessment_score' => 100,
        ],
    ],

    'queue' => [
        /*
         * Supported evaluation calls: evaluator reasoner + evaluator structurer +
         * configured structurer repairs + critic reasoner + critic structurer.
         * resume_timeout must exceed one structured attempt budget + processing margin.
         * evaluation_timeout must exceed all reasoner and structured attempt budgets.
         * queue retry_after/visibility and stale_after must exceed all job timeouts.
         */
        'resume_processing_margin' => (int) env('ASSESSMENT_RESUME_PROCESSING_MARGIN', 30),
        'evaluation_processing_margin' => (int) env('ASSESSMENT_EVALUATION_PROCESSING_MARGIN', 30),
        'retry_after_safety_margin' => (int) env('ASSESSMENT_QUEUE_RETRY_AFTER_SAFETY_MARGIN', 60),
        'resume_timeout' => (int) env(
            'ASSESSMENT_RESUME_JOB_TIMEOUT',
            ((int) env('QWEN_TIMEOUT', 30)
                * (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2))
            + (int) env('ASSESSMENT_RESUME_PROCESSING_MARGIN', 30),
        ),
        'evaluation_timeout' => (int) env(
            'ASSESSMENT_EVALUATION_JOB_TIMEOUT',
            ((int) env('QWEN_REASONER_TIMEOUT', 120)
                * (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2)
                * 2)
            + ((int) env('QWEN_TIMEOUT', 30)
                * (int) env('ASSESSMENT_QWEN_TRANSPORT_ATTEMPTS', 2)
                * (2 + (int) env('ASSESSMENT_EVALUATION_REPAIR_ATTEMPTS', 1)))
            + (int) env('ASSESSMENT_EVALUATION_PROCESSING_MARGIN', 30),
        ),
        'external_work_stale_after' => (int) env(
            'ASSESSMENT_EXTERNAL_WORK_STALE_AFTER',
            (int) env('ASSESSMENT_QUEUE_RETRY_AFTER', 750),
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
