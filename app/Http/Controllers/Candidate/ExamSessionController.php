<?php

namespace App\Http\Controllers\Candidate;

use App\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\FinalizeExamSessionRequest;
use App\Http\Requests\Candidate\RecordExamViolationRequest;
use App\Http\Requests\Candidate\SaveExamSectionRequest;
use App\Models\Campaign;
use App\Models\ExamSession;
use App\QuestionStatus;
use App\Services\ExamSessionService;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    public function __construct(
        private ExamSessionService $sessions,
    ) {}

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $campaign = $this->accessibleCampaign($request, $campaign);
        $this->sessions->startSession($request->user(), $campaign);

        return redirect()->route('candidate.campaigns.exam', $campaign);
    }

    public function update(SaveExamSectionRequest $request, Campaign $campaign, ExamSession $examSession): RedirectResponse
    {
        $campaign = $this->accessibleCampaign($request, $campaign);
        $this->assertSessionOwnership($request, $examSession, $campaign);

        $this->sessions->saveCurrentSectionAnswers(
            $examSession,
            $campaign,
            $request->validated('answers'),
        );

        return back();
    }

    public function advance(Request $request, Campaign $campaign, ExamSession $examSession): RedirectResponse
    {
        $campaign = $this->accessibleCampaign($request, $campaign);
        $this->assertSessionOwnership($request, $examSession, $campaign);

        $this->sessions->advanceSection($examSession, $campaign);

        return back();
    }

    public function storeViolation(RecordExamViolationRequest $request, Campaign $campaign, ExamSession $examSession): RedirectResponse
    {
        $campaign = $this->accessibleCampaign($request, $campaign);
        $this->assertSessionOwnership($request, $examSession, $campaign);

        $result = $this->sessions->recordViolation(
            $examSession,
            $campaign,
            $request->validated('type'),
        );

        if ($result['auto_submitted'] && $result['assessment'] !== null) {
            return to_route('candidate.assessments.show', $result['assessment']);
        }

        return back();
    }

    public function finalize(FinalizeExamSessionRequest $request, Campaign $campaign, ExamSession $examSession): RedirectResponse
    {
        $campaign = $this->accessibleCampaign($request, $campaign);
        $this->assertSessionOwnership($request, $examSession, $campaign);

        $assessment = $this->sessions->finalizeSession(
            $examSession,
            $campaign,
            $request->file('resume'),
        );

        return to_route('candidate.assessments.show', $assessment);
    }

    private function accessibleCampaign(Request $request, Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->whereKey($campaign->id)
            ->where('status', CampaignStatus::Active->value)
            ->whereHas('team', fn ($query) => $query->where('status', TeamStatus::Active->value))
            ->whereHas('invitations', fn ($query) => $query->acceptedForUser($request->user()))
            ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Approved->value))
            ->firstOrFail();
    }

    private function assertSessionOwnership(Request $request, ExamSession $examSession, Campaign $campaign): void
    {
        abort_unless(
            $examSession->user_id === $request->user()->id
                && $examSession->campaign_id === $campaign->id,
            404,
        );
    }
}
