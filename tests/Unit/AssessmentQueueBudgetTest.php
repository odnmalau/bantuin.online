<?php

test('assessment queue budgets cover supported qwen calls below retry after', function () {
    $qwenTimeout = (int) config('assessment.qwen.timeout');
    $transportAttemptCount = (int) config('assessment.qwen.transport_attempt_count');
    $repairAttempts = (int) config('assessment.evaluation.repair_attempts');
    $resumeProcessingMargin = (int) config('assessment.queue.resume_processing_margin');
    $processingMargin = (int) config('assessment.queue.evaluation_processing_margin');
    $safetyMargin = (int) config('assessment.queue.retry_after_safety_margin');
    $resumeTimeout = (int) config('assessment.queue.resume_timeout');
    $evaluationTimeout = (int) config('assessment.queue.evaluation_timeout');
    $staleAfter = (int) config('assessment.queue.external_work_stale_after');
    $retryAfter = (int) config('queue.connections.database.retry_after');

    $attemptSeconds = $qwenTimeout * $transportAttemptCount;
    $supportedCalls = 1 + $repairAttempts + 1;
    $minimumResumeTimeout = $attemptSeconds + $resumeProcessingMargin;
    $minimumEvaluationTimeout = ($attemptSeconds * $supportedCalls) + $processingMargin;

    expect($transportAttemptCount)->toBeGreaterThan(0)
        ->and($attemptSeconds)->toBe($qwenTimeout * $transportAttemptCount)
        ->and($supportedCalls)->toBe(1 + $repairAttempts + 1)
        ->and($resumeTimeout)->toBe($minimumResumeTimeout)
        ->and($resumeTimeout)->toBeGreaterThan($attemptSeconds)
        ->and($evaluationTimeout)->toBe($minimumEvaluationTimeout)
        ->and($evaluationTimeout)->toBeGreaterThan(30)
        ->and($retryAfter)->toBeGreaterThan($resumeTimeout)
        ->and($retryAfter)->toBeGreaterThan($evaluationTimeout)
        ->and($retryAfter)->toBeGreaterThanOrEqual(max($resumeTimeout, $evaluationTimeout) + $safetyMargin)
        ->and($staleAfter)->toBeGreaterThanOrEqual($retryAfter);
});
