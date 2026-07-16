<?php

namespace App\Jobs;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Services\AssessmentEvaluationPipeline;
use App\Services\AssessmentExternalWorkCoordinator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAssessmentWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public readonly ?int $teamId;

    public function __construct(public Assessment $assessment)
    {
        $teamId = $assessment->campaign()->value('team_id');
        $this->teamId = $teamId === null ? null : (int) $teamId;
        $this->timeout = (int) config('assessment.queue.evaluation_timeout');
    }

    /**
     * Execute the job.
     */
    public function handle(
        AssessmentEvaluationPipeline $pipeline,
        AssessmentExternalWorkCoordinator $coordinator,
    ): void {
        $claimed = $coordinator->claimEvaluation($this->assessment, $this->teamId);

        if ($claimed === null) {
            return;
        }

        $outcome = $pipeline->compute($claimed->assessment);
        $finalized = $coordinator->finalizeEvaluation($claimed->assessment, $claimed->attemptId, $outcome);

        if ($finalized?->status === AssessmentStatus::Approved) {
            SendInterviewInvitationEmail::dispatch($finalized)->afterCommit();
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }
}
