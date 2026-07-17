<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreCandidateResumeRequest;
use App\Models\Campaign;
use App\Services\CampaignInvitationService;
use App\Services\CandidateApplicationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CandidateApplicationController extends Controller
{
    public function store(
        StoreCandidateResumeRequest $request,
        Campaign $campaign,
        CampaignInvitationService $invitations,
        CandidateApplicationService $applications,
    ): RedirectResponse {
        abort_unless($invitations->userCanAccessCampaignExam($request->user(), $campaign), 404);

        $applications->storeResume(
            $request->user(),
            $campaign,
            $request->file('resume'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume saved. You can now start the secure exam.'),
        ]);

        return to_route('candidate.campaigns.exam', $campaign);
    }
}
