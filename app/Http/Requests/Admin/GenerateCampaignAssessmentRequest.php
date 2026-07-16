<?php

namespace App\Http\Requests\Admin;

class GenerateCampaignAssessmentRequest extends AbstractGenerateQuestionsRequest
{
    protected function defaultDifficulty(): string
    {
        return 'mixed';
    }
}
