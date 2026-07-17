<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAssessmentGenerationFailures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateCampaignQuestionRequest;
use App\Models\Campaign;
use App\Models\CampaignSection;
use App\Services\Ai\QwenAssessmentGenerator;
use Illuminate\Http\RedirectResponse;

class CampaignQuestionGenerationController extends Controller
{
    use HandlesAssessmentGenerationFailures;

    /**
     * Generate a draft question for a campaign section.
     */
    public function __invoke(
        GenerateCampaignQuestionRequest $request,
        Campaign $campaign,
        CampaignSection $section,
        QwenAssessmentGenerator $generator,
    ): RedirectResponse {
        return $this->generateDraftsAndRedirect(
            "generation.{$section->id}",
            fn (): int => $generator->generateForSection($campaign, $section, $request->validated()),
            'admin.campaigns.show',
            $campaign,
        );
    }
}
