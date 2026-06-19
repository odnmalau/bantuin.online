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
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentEventRecorder;
use App\Services\AssessmentSettings;
use App\Services\CandidateRankingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    /**
     * Show the HRD assessment workstation.
     */
    public function index(): Response
    {
        return Inertia::render('admin/assessments/index', [
            'assessments' => Assessment::query()
                ->with('user:id,name,email')
                ->orderByRaw('ranking_score is null')
                ->orderByDesc('ranking_score')
                ->latest()
                ->get()
                ->map(fn (Assessment $assessment): array => [
                    'id' => $assessment->id,
                    'candidate_name' => $assessment->user?->name,
                    'candidate_email' => $assessment->user?->email,
                    'ai_score' => $assessment->ai_score,
                    'resume_score' => $assessment->resume_score,
                    'mcq_score' => $assessment->mcq_score,
                    'essay_score' => $assessment->essay_score,
                    'ranking_score' => $assessment->ranking_score,
                    'status' => $assessment->status->value,
                    'created_at' => $assessment->created_at,
                    'evaluated_at' => $assessment->evaluated_at,
                ]),
        ]);
    }

    /**
     * Show a single assessment review page.
     */
    public function show(
        Assessment $assessment,
        AssessmentSettings $settings,
        CandidateRankingCalculator $rankingCalculator,
    ): Response {
        $assessment->loadMissing([
            'user:id,name,email',
            'approver:id,name,email',
            'campaign:id,title,threshold_score',
            'events.actor:id,name,email',
        ]);
        $sortedEvents = $assessment->events->sortByDesc('occurred_at')->values();
        $latestOverrideEvent = $sortedEvents->firstWhere('type', 'admin_overrode_ranking_score');
        $canReview = $this->canReview($assessment);

        return Inertia::render('admin/assessments/show', [
            'assessment' => [
                'id' => $assessment->id,
                'candidate' => [
                    'name' => $assessment->user?->name,
                    'email' => $assessment->user?->email,
                ],
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
                'audit' => [
                    'provider' => config('assessment.qwen.provider'),
                    'model' => config('assessment.qwen.model'),
                    'threshold' => $settings->passingScoreFor($assessment),
                    'threshold_source' => $settings->passingScoreSource($assessment),
                    'global_passing_score' => $settings->passingScore(),
                    'ranking_formula' => data_get($assessment->ranking_payload, 'formula', $rankingCalculator->configuredFormula()),
                    'override_reason' => data_get($latestOverrideEvent?->payload, 'reason'),
                    'override_score' => data_get($latestOverrideEvent?->payload, 'to_score'),
                ],
                'events' => $sortedEvents
                    ->map(fn ($event): array => [
                        'id' => $event->id,
                        'type' => $event->type,
                        'title' => $event->title,
                        'description' => $event->description,
                        'payload' => $event->payload,
                        'occurred_at' => $event->occurred_at,
                        'actor' => $event->actor?->only(['name', 'email']),
                    ]),
            ],
        ]);
    }

    /**
     * Retry a failed AI evaluation.
     */
    public function retryEvaluation(
        RetryAssessmentEvaluationRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->denyUnless(
            $this->canRetry($assessment),
            'Only failed evaluations can be retried.',
        );

        $previousStatus = $assessment->status;
        $validated = $request->validated();

        $assessment->resetEvaluationForRetry();

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_retried_evaluation',
            title: __('Admin retried evaluation'),
            description: __('Admin queued a fresh assessment evaluation job.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::Submitted,
            payload: ['reason' => $validated['reason'] ?? null],
        );

        EvaluateAssessmentWithAi::dispatch($assessment);

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Retry sending the interview invitation email after a delivery failure.
     */
    public function retryEmail(
        RetryInterviewEmailRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->denyUnless(
            $this->canRetryEmail($assessment),
            'Only failed email deliveries can be retried.',
        );

        $validated = $request->validated();
        $previousStatus = $assessment->status;

        $assessment->update([
            'status' => AssessmentStatus::Approved,
        ]);

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_retried_email',
            title: __('Admin retried interview email'),
            description: __('Admin queued a fresh interview invitation email job.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::Approved,
            payload: ['reason' => $validated['reason'] ?? null],
        );

        SendInterviewInvitationEmail::dispatch($assessment);

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Promote a false negative to interview review.
     */
    public function promote(
        PromoteAssessmentRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->denyUnless(
            $this->canPromote($assessment),
            'Only evaluated assessments can be promoted to interview review.',
        );

        $validated = $request->validated();
        $promotion = $this->promotionDetails(
            $assessment,
            $validated,
        );

        $previousStatus = $assessment->status;
        $assessment->update($promotion['updates']);

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_promoted',
            title: __('Admin promoted assessment'),
            description: __('Admin promoted a candidate to interview review.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::PendingApproval,
            payload: [
                'reason' => $validated['reason'],
                'manual_email_supplied' => $promotion['manual_email_supplied'],
            ],
        );

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Override the final ranking score with an Admin reason.
     */
    public function overrideScore(
        OverrideAssessmentScoreRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->denyUnless(
            $this->canReview($assessment),
            'Only reviewable assessments can have their ranking score overridden.',
        );

        $validated = $request->validated();
        $previousStatus = $assessment->status;
        $previousScore = $assessment->ranking_score;
        $override = [
            'from_score' => $previousScore,
            'to_score' => (int) $validated['ranking_score'],
            'reason' => $validated['reason'],
            'actor_id' => $request->user()->id,
            'occurred_at' => now()->toISOString(),
        ];

        $assessment->update([
            'ranking_score' => (int) $validated['ranking_score'],
            'ranking_payload' => [
                ...($assessment->ranking_payload ?? []),
                'override' => $override,
            ],
            'status' => AssessmentStatus::Overridden,
        ]);

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_overrode_ranking_score',
            title: __('Admin overrode ranking score'),
            description: __('Admin replaced the backend ranking score with a manual override.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::Overridden,
            payload: $override,
        );

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Approve an assessment and queue the interview email.
     */
    public function approve(
        ApproveAssessmentRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->ensureReviewable($assessment);
        $previousStatus = $assessment->status;
        $validated = $request->validated();

        $assessment->update([
            'approved_email_subject' => $validated['email_subject'],
            'approved_email_body' => $validated['email_body'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'status' => AssessmentStatus::Approved,
        ]);

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_approved',
            title: __('Admin approved assessment'),
            description: __('Admin approved the candidate for interview and queued the email job.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::Approved,
            payload: ['email_subject' => $validated['email_subject']],
        );

        SendInterviewInvitationEmail::dispatch($assessment);

        return to_route('admin.assessments.show', $assessment);
    }

    /**
     * Reject an assessment without sending email.
     */
    public function reject(
        RejectAssessmentRequest $request,
        Assessment $assessment,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $this->ensureReviewable($assessment);
        $previousStatus = $assessment->status;
        $validated = $request->validated();

        $assessment->update([
            'status' => AssessmentStatus::Rejected,
            'rejected_at' => now(),
        ]);

        $this->recordStatusTransition(
            events: $events,
            assessment: $assessment,
            actor: $request->user(),
            type: 'admin_rejected',
            title: __('Admin rejected assessment'),
            description: __('Admin rejected the assessment without sending an interview email.'),
            fromStatus: $previousStatus,
            toStatus: AssessmentStatus::Rejected,
            payload: ['reason' => $validated['reason']],
        );

        return to_route('admin.assessments.show', $assessment);
    }

    private function ensureReviewable(Assessment $assessment): void
    {
        $this->denyUnless(
            $this->canReview($assessment),
            'Only reviewable assessments can be approved or rejected.',
        );
    }

    private function canReview(Assessment $assessment): bool
    {
        return $assessment->status->isReviewable();
    }

    private function canRetry(Assessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::EvaluationFailed;
    }

    private function canRetryEmail(Assessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::EmailFailed
            && filled($assessment->approved_email_subject)
            && filled($assessment->approved_email_body);
    }

    private function canPromote(Assessment $assessment): bool
    {
        return $assessment->status->isPromotable();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     updates: array<string, mixed>,
     *     manual_email_supplied: bool
     * }
     */
    private function promotionDetails(Assessment $assessment, array $validated): array
    {
        $manualSubject = trim((string) ($validated['email_subject'] ?? ''));
        $manualBody = trim((string) ($validated['email_body'] ?? ''));
        $manualEmailSupplied = $manualSubject !== '' && $manualBody !== '';
        $hasExistingDraft = filled($assessment->ai_email_subject) && filled($assessment->ai_email_body);

        if (($manualSubject !== '') !== ($manualBody !== '')) {
            throw ValidationException::withMessages([
                'email_subject' => __('Provide both subject and body for the manual email draft.'),
                'email_body' => __('Provide both subject and body for the manual email draft.'),
            ]);
        }

        if (! $hasExistingDraft && ! $manualEmailSupplied) {
            throw ValidationException::withMessages([
                'email_subject' => __('A manual email subject is required because no AI draft exists.'),
                'email_body' => __('A manual email body is required because no AI draft exists.'),
            ]);
        }

        $updates = [
            'status' => AssessmentStatus::PendingApproval,
        ];

        if ($manualEmailSupplied) {
            $updates['ai_email_subject'] = $manualSubject;
            $updates['ai_email_body'] = $manualBody;
        }

        return [
            'updates' => $updates,
            'manual_email_supplied' => $manualEmailSupplied,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordStatusTransition(
        AssessmentEventRecorder $events,
        Assessment $assessment,
        ?User $actor,
        string $type,
        string $title,
        string $description,
        AssessmentStatus $fromStatus,
        AssessmentStatus $toStatus,
        array $payload = [],
    ): void {
        $events->record(
            assessment: $assessment,
            type: $type,
            title: $title,
            description: $description,
            payload: $this->statusTransitionPayload($fromStatus, $toStatus, $payload),
            actor: $actor,
        );
    }

    private function denyUnless(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([
                'assessment' => __($message),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function statusTransitionPayload(
        AssessmentStatus $fromStatus,
        AssessmentStatus $toStatus,
        array $payload = [],
    ): array {
        return [
            ...$payload,
            'from_status' => $fromStatus->value,
            'to_status' => $toStatus->value,
        ];
    }
}
