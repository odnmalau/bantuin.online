<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateCampaignAssessmentRequest;
use App\Models\Campaign;
use App\Services\Ai\QwenAssessmentGenerator;
use Illuminate\Http\RedirectResponse;

class CampaignAssessmentGenerationController extends Controller
{
    use HandlesAssessmentGenerationFailures;

    /**
     * Generate draft campaign sections and questions.
     */
    public function store(
        GenerateCampaignAssessmentRequest $request,
        Campaign $campaign,
        QwenAssessmentGenerator $generator,
    ): RedirectResponse {
        return $this->generateDraftsAndRedirect(
            'generation',
            fn (): int => $generator->generate($campaign, $request->validated()),
            'admin.campaigns.show',
            $campaign,
        );
    }
}
