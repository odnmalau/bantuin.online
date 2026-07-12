<?php

namespace App\Http\Controllers\Admin;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAssessmentRequest;
use App\Http\Requests\Admin\OverrideAssessmentScoreRequest;
use App\Http\Requests\Admin\PromoteAssessmentRequest;
use App\Http\Requests\Admin\RejectAssessmentRequest;
use App\Http\Requests\Admin\RetryAssessmentEvaluationRequest;
use App\Http\Requests\Admin\RetryInterviewEmailRequest;
use App\Models\Assessment;
use App\Services\AssessmentWorkflowService;
use App\TeamStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    /**
     * Show a single assessment review page.
     */
    public function show(Assessment $assessment): Response
    {
        $assessment->loadMissing([
            'user:id,name,email',
            'approver:id,name,email',
            'campaign:id,team_id,title,role_title,threshold_score',
            'campaign.team:id,status',
        ]);
        $canReview = $this->canReview($assessment);
        $leaderboardRank = $this->leaderboardRankFor($assessment);

        return Inertia::render('admin/assessments/show', [
            'assessment' => [
                'id' => $assessment->id,
                'candidate' => [
                    'name' => $assessment->user?->name,
                    'email' => $assessment->user?->email,
                ],
                'campaign' => [
                    'title' => $assessment->campaign?->title,
                    'role_title' => $assessment->campaign?->role_title,
                ],
                'rank' => $leaderboardRank,
                'approver' => $assessment->approver?->only(['name', 'email']),
                'answers_payload' => $assessment->answers_payload,
                'resume_original_name' => $assessment->resume_original_name,
                'resume_score' => $assessment->resume_score,
                'resume_justification' => $assessment->resume_justification,
                'resume_payload' => $assessment->resume_payload,
                'needs_manual_review' => $assessment->needs_manual_review,
                'ai_score' => $assessment->ai_score,
                'mcq_score' => $assessment->mcq_score,
                'essay_score' => $assessment->essay_score,
                'ranking_score' => $assessment->ranking_score,
                'ranking_payload' => $assessment->ranking_payload,
                'section_scores' => data_get($assessment->ranking_payload, 'section_scores', []),
                'critic_payload' => $assessment->critic_payload,
                'ai_justification' => $assessment->ai_justification,
                'ai_email_subject' => $assessment->ai_email_subject,
                'ai_email_body' => $assessment->ai_email_body,
                'approved_email_subject' => $assessment->approved_email_subject,
                'approved_email_body' => $assessment->approved_email_body,
                'status' => $assessment->status->value,
                'can_review' => $canReview,
                'can_retry' => $this->canRetry($assessment),
                'can_retry_email' => $this->canRetryEmail($assessment),
                'can_promote' => $this->canPromote($assessment),
                'can_override_score' => $canReview,
                'created_at' => $assessment->created_at,
                'evaluated_at' => $assessment->evaluated_at,
                'approved_at' => $assessment->approved_at,
                'rejected_at' => $assessment->rejected_at,
                'email_sent_at' => $assessment->email_sent_at,
            ],
        ]);
    }

    /**
     * Resolve the candidate's current leaderboard rank among scored assessments.
     */
    private function leaderboardRankFor(Assessment $assessment): ?int
    {
        if ($assessment->ranking_score === null) {
            return null;
        }

        $higherRankedCount = Assessment::query()
            ->where('campaign_id', $assessment->campaign_id)
            ->whereNotNull('ranking_score')
            ->where(function ($query) use ($assessment): void {
                $query
                    ->where('ranking_score', '>', $assessment->ranking_score)
                    ->orWhere(function ($tiedQuery) use ($assessment): void {
                        $tiedQuery
                            ->where('ranking_score', $assessment->ranking_score)
                            ->where('id', '>', $assessment->id);
                    });
            })
            ->count();

        return $higherRankedCount + 1;
    }

    /**
     * Retry a failed AI evaluation.
     */
    public function retryEvaluation(
        RetryAssessmentEvaluationRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->retryEvaluation($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Retry sending the interview invitation email after a delivery failure.
     */
    public function retryEmail(
        RetryInterviewEmailRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->retryEmail($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Promote a false negative to interview review.
     */
    public function promote(
        PromoteAssessmentRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->promote($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Override the final ranking score with an Admin reason.
     */
    public function overrideScore(
        OverrideAssessmentScoreRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->overrideScore($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Approve an assessment and queue the interview email.
     */
    public function approve(
        ApproveAssessmentRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->approve($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Reject an assessment without sending email.
     */
    public function reject(
        RejectAssessmentRequest $request,
        Assessment $assessment,
        AssessmentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->reject($assessment, $request->user(), $request->validated());

        return to_route('admin.assessments.show', $assessment);
    }

    private function canReview(Assessment $assessment): bool
    {
        return $this->campaignTeamIsWritable($assessment) && $assessment->status->isReviewable();
    }

    private function canRetry(Assessment $assessment): bool
    {
        return $this->campaignTeamIsWritable($assessment)
            && $assessment->status === AssessmentStatus::EvaluationFailed;
    }

    private function canRetryEmail(Assessment $assessment): bool
    {
        return $this->campaignTeamIsWritable($assessment)
            && $assessment->status === AssessmentStatus::EmailFailed
            && filled($assessment->approved_email_subject)
            && filled($assessment->approved_email_body);
    }

    private function canPromote(Assessment $assessment): bool
    {
        return $this->campaignTeamIsWritable($assessment) && $assessment->status->isPromotable();
    }

    private function campaignTeamIsWritable(Assessment $assessment): bool
    {
        return $assessment->campaign?->team?->status === TeamStatus::Active;
    }
}
