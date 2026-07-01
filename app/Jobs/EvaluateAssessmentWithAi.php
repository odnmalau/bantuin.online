<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\AssessmentEvaluationPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAssessmentWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Assessment $assessment) {}

    /**
     * Execute the job.
     */
    public function handle(AssessmentEvaluationPipeline $pipeline): void
    {
        $pipeline->evaluate($this->assessment);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }
}
