<?php

namespace App\Http\Requests\Admin;

use App\Models\Campaign;

class GenerateCampaignAssessmentRequest extends AbstractGenerateQuestionsRequest
{
    protected function defaultLanguage(): string
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return $campaign->language ?? 'English';
    }

    protected function defaultDifficulty(): string
    {
        return 'mixed';
    }
}
