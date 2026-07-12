<?php

namespace App\Services;

use App\Models\Assessment;

final readonly class ClaimedAssessmentWork
{
    public function __construct(
        public Assessment $assessment,
        public string $attemptId,
    ) {}
}
