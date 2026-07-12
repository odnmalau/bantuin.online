<?php

test('assessment queue budgets cover supported qwen calls below retry after', function () {
    $qwenTimeout = (int) config('assessment.qwen.timeout');
    $transportAttemptCount = (int) config('assessment.qwen.transport_attempt_count');
    $repairAttempts = (int) config('assessment.evaluation.repair_attempts');
    $processingMargin = (int) config('assessment.queue.evaluation_processing_margin');
    $safetyMargin = (int) config('assessment.queue.retry_after_safety_margin');
    $evaluationTimeout = (int) config('assessment.queue.evaluation_timeout');
    $retryAfter = (int) config('queue.connections.database.retry_after');

    $attemptSeconds = $qwenTimeout * $transportAttemptCount;
    $supportedCalls = 1 + $repairAttempts + 1;
    $minimumEvaluationTimeout = ($attemptSeconds * $supportedCalls) + $processingMargin;

    expect($transportAttemptCount)->toBeGreaterThan(0)
        ->and($attemptSeconds)->toBe($qwenTimeout * $transportAttemptCount)
        ->and($supportedCalls)->toBe(1 + $repairAttempts + 1)
        ->and($evaluationTimeout)->toBe($minimumEvaluationTimeout)
        ->and($evaluationTimeout)->toBeGreaterThan(30)
        ->and($retryAfter)->toBeGreaterThan($evaluationTimeout)
        ->and($retryAfter)->toBeGreaterThanOrEqual($evaluationTimeout + $safetyMargin);
});
