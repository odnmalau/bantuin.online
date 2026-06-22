<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionStatus;
use App\Services\AssessmentSubmissionBuilder;
use App\Services\CampaignInvitationService;
use App\Services\ExamSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private CampaignInvitationService $invitations,
        private ExamSessionService $examSessions,
        private AssessmentSubmissionBuilder $submissionBuilder,
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
        abort_unless($assessment->user_id === $request->user()->id, 403);

        $assessment->loadMissing('campaign:id,title,role_title');

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
        $examSession = null;
        $sectionSummaries = [];
        $currentSection = null;
        $questions = [];

        if ($campaign !== null) {
            $session = $this->examSessions->findActiveSession($request->user(), $campaign);

            if ($session !== null) {
                $examSession = $this->examSessions->sessionPayloadForInertia($session, $campaign);
                $session = $session->fresh();
            }

            $orderedSections = $this->examSessions->orderedExamSections($campaign);

            $sectionSummaries = $orderedSections
                ->map(fn (CampaignSection $section): array => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'duration_minutes' => $section->duration_minutes,
                    'sort_order' => $section->sort_order,
                    'question_count' => $section->questions->count(),
                ])
                ->all();

            if ($session !== null && $session->current_section_id !== null) {
                $active = $orderedSections->firstWhere('id', $session->current_section_id);

                if ($active !== null) {
                    $currentSection = $this->sectionForCandidate($active);
                    $questions = $currentSection['questions'];
                }
            }
        }

        $assessment = $this->currentAssessmentForExam($request, $campaign);

        return Inertia::render('candidate/exam', [
            'campaign' => $this->campaignSummaryForExam($campaign),
            'sections' => $sectionSummaries,
            'currentSection' => $currentSection,
            'questions' => $questions,
            'examSession' => $examSession,
            'assessment' => $this->assessmentSummaryForExam($assessment),
        ]);
    }

    private function accessibleCampaignForExam(Request $request, Campaign $campaign): Campaign
    {
        abort_unless($this->invitations->userCanAccessCampaignExam($request->user(), $campaign), 403);

        return $this->campaignForExam($campaign);
    }

    private function campaignForExam(Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->whereKey($campaign->id)
            ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Approved->value))
            ->with([
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.questions' => fn ($query) => $query
                    ->where('status', QuestionStatus::Approved->value)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();
    }

    private function currentAssessmentForExam(Request $request, ?Campaign $campaign): ?Assessment
    {
        if ($campaign === null) {
            return null;
        }

        return $request->user()
            ->assessments()
            ->whereBelongsTo($campaign)
            ->latest()
            ->first();
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
     * @return array{title: string, role_title: string}|null
     */
    private function campaignSummaryForAssessment(Assessment $assessment): ?array
    {
        if ($assessment->campaign === null) {
            return null;
        }

        return [
            'title' => $assessment->campaign->title,
            'role_title' => $assessment->campaign->role_title,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function campaignSummaryForExam(?Campaign $campaign): ?array
    {
        if ($campaign === null) {
            return null;
        }

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'role_title' => $campaign->role_title,
            'seniority' => $campaign->seniority,
            'threshold_score' => $campaign->threshold_score,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assessmentSummaryForExam(?Assessment $assessment): ?array
    {
        if ($assessment === null) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'created_at' => $assessment->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionForCandidate(CampaignSection $section): array
    {
        $questions = $section->questions
            ->map(fn (CampaignQuestion $question): array => $this->submissionBuilder->questionForCandidate($question))
            ->values()
            ->all();

        return [
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'duration_minutes' => $section->duration_minutes,
            'sort_order' => $section->sort_order,
            'question_count' => count($questions),
            'questions' => $questions,
        ];
    }
}
