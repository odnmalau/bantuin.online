<?php

namespace App\Http\Controllers\Candidate;

use App\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Campaign;
use App\QuestionStatus;
use App\Services\CampaignInvitationService;
use App\Services\CandidateExamPage;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private CampaignInvitationService $invitations,
        private CandidateExamPage $examPage,
    ) {}

    /**
     * Redirect exam entry to a single accessible campaign when possible.
     */
    public function redirectExam(Request $request): RedirectResponse|Response
    {
        $accessibleCampaigns = $this->invitations->accessibleCampaignsForUser($request->user());

        if ($accessibleCampaigns->count() === 1) {
            return redirect()->route('candidate.campaigns.exam', $accessibleCampaigns->first());
        }

        if ($accessibleCampaigns->count() > 1) {
            return Inertia::render(
                'candidate/exam',
                $this->examPage->picker($request->user(), $accessibleCampaigns),
            );
        }

        return $this->renderExam($request, null);
    }

    /**
     * Show the candidate exam form for an assigned campaign.
     */
    public function campaignExam(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->accessibleCampaignForExam($request, $campaign);

        return $this->renderExam($request, $campaign);
    }

    /**
     * Show an assessment status page.
     */
    public function show(Request $request, Assessment $assessment): Response
    {
        $assessment = Assessment::query()
            ->whereKey($assessment->id)
            ->whereBelongsTo($request->user())
            ->whereHas('campaign.invitations', fn ($query) => $query->acceptedForUser($request->user()))
            ->with('campaign:id,team_id,title,role_title', 'campaign.team:id,name')
            ->firstOrFail();

        return Inertia::render('candidate/assessments/show', [
            'assessment' => [
                'id' => $assessment->id,
                'campaign' => $this->campaignSummaryForAssessment($assessment),
                'campaign_id' => $assessment->campaign_id,
                'answers_payload' => collect($assessment->answers_payload ?? [])
                    ->map(fn (mixed $answer): array => $this->answerSnapshotForCandidate($answer))
                    ->all(),
                'resume_original_name' => $assessment->resume_original_name,
                'resume_score' => $assessment->resume_score,
                'ai_score' => $assessment->ai_score,
                'ai_justification' => $assessment->ai_justification,
                'status' => $assessment->status->value,
                'created_at' => $assessment->created_at,
                'evaluated_at' => $assessment->evaluated_at,
                'email_sent_at' => $assessment->email_sent_at,
            ],
        ]);
    }

    private function renderExam(Request $request, ?Campaign $campaign): Response
    {
        return Inertia::render('candidate/exam', $this->examPage->for($request->user(), $campaign));
    }

    private function accessibleCampaignForExam(Request $request, Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->whereKey($campaign->id)
            ->where('status', CampaignStatus::Active->value)
            ->whereHas('team', fn ($query) => $query->where('status', TeamStatus::Active->value))
            ->whereHas('invitations', fn ($query) => $query->acceptedForUser($request->user()))
            ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Approved->value))
            ->with([
                'team:id,name',
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.questions' => fn ($query) => $query
                    ->where('status', QuestionStatus::Approved->value)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();
    }

    /**
     * @return array{question_id: int|null, question: string, answer: string}
     */
    private function answerSnapshotForCandidate(mixed $answer): array
    {
        return [
            'question_id' => data_get($answer, 'question_id'),
            'question' => (string) data_get($answer, 'question', ''),
            'answer' => (string) data_get($answer, 'answer', ''),
        ];
    }

    /**
     * @return array{title: string, role_title: string, team: array{name: string}}|null
     */
    private function campaignSummaryForAssessment(Assessment $assessment): ?array
    {
        if ($assessment->campaign === null) {
            return null;
        }

        return [
            'title' => $assessment->campaign->title,
            'role_title' => $assessment->campaign->role_title,
            'team' => [
                'name' => $assessment->campaign->team->name,
            ],
        ];
    }
}
