<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\SendInterviewInvitationEmail;
use App\Models\Assessment;
use App\Models\Team;
use App\Models\User;
use App\TeamStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentWorkflowService
{
    public function __construct(private AssessmentEventRecorder $events) {}

    /**
     * @param  array{reason?: string|null}  $validated
     */
    public function retryEvaluation(Assessment $assessment, User $actor, array $validated = []): Assessment
    {
        $updated = DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status === AssessmentStatus::EvaluationFailed,
                'Only failed evaluations can be retried.',
            );

            $previousStatus = $locked->status;
            $locked->resetEvaluationForRetry();

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
                type: 'admin_retried_evaluation',
                title: __('Admin retried evaluation'),
                description: __('Admin queued a fresh assessment evaluation job.'),
                fromStatus: $previousStatus,
                toStatus: AssessmentStatus::Submitted,
                payload: ['reason' => $validated['reason'] ?? null],
            );

            return $locked->fresh() ?? $locked;
        }, attempts: 3);

        EvaluateAssessmentWithAi::dispatch($updated);

        return $updated;
    }

    /**
     * @param  array{reason?: string|null}  $validated
     */
    public function retryEmail(Assessment $assessment, User $actor, array $validated = []): Assessment
    {
        $updated = DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status === AssessmentStatus::EmailFailed
                    && filled($locked->approved_email_subject)
                    && filled($locked->approved_email_body),
                'Only failed email deliveries can be retried.',
            );

            $previousStatus = $locked->status;

            $locked->update([
                'status' => AssessmentStatus::Approved,
            ]);

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
                type: 'admin_retried_email',
                title: __('Admin retried interview email'),
                description: __('Admin queued a fresh interview invitation email job.'),
                fromStatus: $previousStatus,
                toStatus: AssessmentStatus::Approved,
                payload: ['reason' => $validated['reason'] ?? null],
            );

            return $locked->fresh() ?? $locked;
        }, attempts: 3);

        SendInterviewInvitationEmail::dispatch($updated);

        return $updated;
    }

    /**
     * @param  array{reason: string, email_subject?: string|null, email_body?: string|null}  $validated
     */
    public function promote(Assessment $assessment, User $actor, array $validated): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status->isPromotable(),
                'Only evaluated assessments can be promoted to interview review.',
            );

            $promotion = $this->promotionDetails($locked, $validated);
            $previousStatus = $locked->status;
            $locked->update($promotion['updates']);

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
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

            return $locked->fresh() ?? $locked;
        }, attempts: 3);
    }

    /**
     * @param  array{ranking_score: int, reason: string}  $validated
     */
    public function overrideScore(Assessment $assessment, User $actor, array $validated): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status->isReviewable(),
                'Only reviewable assessments can have their ranking score overridden.',
            );

            $previousStatus = $locked->status;
            $previousScore = $locked->ranking_score;
            $override = [
                'from_score' => $previousScore,
                'to_score' => (int) $validated['ranking_score'],
                'reason' => $validated['reason'],
                'actor_id' => $actor->id,
                'occurred_at' => now()->toISOString(),
            ];

            $locked->update([
                'ranking_score' => (int) $validated['ranking_score'],
                'ranking_payload' => [
                    ...($locked->ranking_payload ?? []),
                    'override' => $override,
                ],
                'status' => AssessmentStatus::Overridden,
            ]);

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
                type: 'admin_overrode_ranking_score',
                title: __('Admin overrode ranking score'),
                description: __('Admin replaced the backend ranking score with a manual override.'),
                fromStatus: $previousStatus,
                toStatus: AssessmentStatus::Overridden,
                payload: $override,
            );

            return $locked->fresh() ?? $locked;
        }, attempts: 3);
    }

    /**
     * @param  array{email_subject: string, email_body: string}  $validated
     */
    public function approve(Assessment $assessment, User $actor, array $validated): Assessment
    {
        $updated = DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status->isReviewable(),
                'Only reviewable assessments can be approved or rejected.',
            );

            $previousStatus = $locked->status;

            $locked->update([
                'approved_email_subject' => $validated['email_subject'],
                'approved_email_body' => $validated['email_body'],
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'status' => AssessmentStatus::Approved,
            ]);

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
                type: 'admin_approved',
                title: __('Admin approved assessment'),
                description: __('Admin approved the candidate for interview and queued the email job.'),
                fromStatus: $previousStatus,
                toStatus: AssessmentStatus::Approved,
                payload: ['email_subject' => $validated['email_subject']],
            );

            return $locked->fresh() ?? $locked;
        }, attempts: 3);

        SendInterviewInvitationEmail::dispatch($updated);

        return $updated;
    }

    /**
     * @param  array{reason: string}  $validated
     */
    public function reject(Assessment $assessment, User $actor, array $validated): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor, $validated): Assessment {
            $locked = $this->lockWritableAssessment($assessment);

            $this->denyUnless(
                $locked->status->isReviewable(),
                'Only reviewable assessments can be approved or rejected.',
            );

            $previousStatus = $locked->status;

            $locked->update([
                'status' => AssessmentStatus::Rejected,
                'rejected_at' => now(),
            ]);

            $this->recordStatusTransition(
                assessment: $locked,
                actor: $actor,
                type: 'admin_rejected',
                title: __('Admin rejected assessment'),
                description: __('Admin rejected the assessment without sending an interview email.'),
                fromStatus: $previousStatus,
                toStatus: AssessmentStatus::Rejected,
                payload: ['reason' => $validated['reason']],
            );

            return $locked->fresh() ?? $locked;
        }, attempts: 3);
    }

    private function lockWritableAssessment(Assessment $assessment): Assessment
    {
        $assessment->loadMissing('campaign');

        if ($assessment->campaign === null) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment is no longer available.'),
            ]);
        }

        $team = Team::query()->whereKey($assessment->campaign->team_id)->lockForUpdate()->firstOrFail();

        $locked = Assessment::query()
            ->whereKey($assessment->id)
            ->lockForUpdate()
            ->firstOrFail();

        $locked->loadMissing('campaign.team');

        if ($locked->campaign?->team_id !== $team->id || $team->status !== TeamStatus::Active) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment is no longer available.'),
            ]);
        }

        return $locked;
    }

    /**
     * @param  array{reason: string, email_subject?: string|null, email_body?: string|null}  $validated
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
        Assessment $assessment,
        ?User $actor,
        string $type,
        string $title,
        string $description,
        AssessmentStatus $fromStatus,
        AssessmentStatus $toStatus,
        array $payload = [],
    ): void {
        $this->events->record(
            assessment: $assessment,
            type: $type,
            title: $title,
            description: $description,
            payload: [
                ...$payload,
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
            ],
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
}
