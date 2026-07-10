<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Campaign;

class AssessmentThreshold
{
    public function passingScoreFor(Assessment $assessment): int
    {
        $campaign = $this->campaignFor($assessment);

        if ($campaign !== null) {
            return (int) $campaign->threshold_score;
        }

        return $this->defaultPassingScore();
    }

    /**
     * @return 'campaign'|'config'
     */
    public function passingScoreSource(Assessment $assessment): string
    {
        return $this->campaignFor($assessment) === null ? 'config' : 'campaign';
    }

    public function defaultPassingScore(): int
    {
        return (int) config('assessment.threshold', 75);
    }

    private function campaignFor(Assessment $assessment): ?Campaign
    {
        $assessment->loadMissing('campaign');

        return $assessment->campaign;
    }
}
