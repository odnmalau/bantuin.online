<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Team;
use App\Services\AssessmentEvaluationPipeline;
use App\TeamStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EvaluateAssessmentWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public readonly ?int $teamId;

    public function __construct(public Assessment $assessment)
    {
        $teamId = $assessment->campaign()->value('team_id');
        $this->teamId = $teamId === null ? null : (int) $teamId;
    }

    /**
     * Execute the job.
     */
    public function handle(AssessmentEvaluationPipeline $pipeline): void
    {
        DB::transaction(function () use ($pipeline): void {
            if ($this->teamId !== null) {
                Team::query()->whereKey($this->teamId)->lockForUpdate()->firstOrFail();
            }

            $assessment = $this->assessment->fresh('campaign.team');

            if ($assessment === null
                || ($this->teamId !== null && ($assessment->campaign?->team_id !== $this->teamId
                    || $assessment->campaign->team?->status !== TeamStatus::Active))) {
                return;
            }

            $pipeline->evaluate($assessment);
        }, attempts: 3);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }
}
