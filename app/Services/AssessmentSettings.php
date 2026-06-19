<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Assessment;
use App\Models\Campaign;

class AssessmentSettings
{
    public function passingScore(): int
    {
        return ApplicationSetting::integer(
            ApplicationSetting::AssessmentPassingScore,
            (int) config('assessment.threshold', 75),
        );
    }

    public function passingScoreFor(Assessment $assessment): int
    {
        $campaign = $this->campaignFor($assessment);

        if ($campaign !== null) {
            return (int) $campaign->threshold_score;
        }

        return $this->passingScore();
    }

    /**
     * @return 'campaign'|'global'
     */
    public function passingScoreSource(Assessment $assessment): string
    {
        return $this->campaignFor($assessment) === null ? 'global' : 'campaign';
    }

    private function campaignFor(Assessment $assessment): ?Campaign
    {
        $assessment->loadMissing('campaign');

        return $assessment->campaign;
    }

    public function updatePassingScore(int $passingScore): void
    {
        ApplicationSetting::setInteger(
            ApplicationSetting::AssessmentPassingScore,
            $passingScore,
        );
    }
}
