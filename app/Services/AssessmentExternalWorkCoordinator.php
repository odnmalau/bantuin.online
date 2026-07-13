<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Team;
use App\TeamStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentExternalWorkCoordinator
{
    public function __construct(private AssessmentEventRecorder $events) {}

    public function claimResumeScreening(Assessment $assessment, ?int $expectedTeamId): ?ClaimedAssessmentWork
    {
        return DB::transaction(function () use ($assessment, $expectedTeamId): ?ClaimedAssessmentWork {
            $locked = $this->lockAssessmentForTeam($assessment, $expectedTeamId);

            if ($locked === null
                || ! $this->resumeScreeningIsClaimable($locked)
                || blank($locked->resume_path)) {
                return null;
            }

            $attemptId = (string) Str::uuid();

            $locked->update([
                'status' => AssessmentStatus::ResumeProcessing,
                'resume_screening_attempt_id' => $attemptId,
                'resume_screening_started_at' => now(),
            ]);

            $this->events->record(
                assessment: $locked,
                type: 'resume_processing_started',
                title: __('Resume processing started'),
                description: __('The private resume PDF is being extracted for screening.'),
            );

            return new ClaimedAssessmentWork($locked->fresh(['user', 'campaign.team']) ?? $locked, $attemptId);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{type: string, title: string, description: string, payload?: array<string, mixed>}>  $events
     */
    public function finalizeResumeScreening(
        Assessment $assessment,
        string $attemptId,
        array $attributes,
        array $events,
    ): bool {
        return DB::transaction(function () use ($assessment, $attemptId, $attributes, $events): bool {
            $locked = $this->lockAssessmentForTeam($assessment, $assessment->campaign?->team_id);

            if ($locked === null
                || $locked->status !== AssessmentStatus::ResumeProcessing
                || $locked->resume_screening_attempt_id !== $attemptId) {
                return false;
            }

            $locked->update([
                ...$attributes,
                'resume_screening_attempt_id' => null,
                'resume_screening_started_at' => null,
            ]);

            foreach ($events as $event) {
                $this->events->record(
                    assessment: $locked,
                    type: $event['type'],
                    title: $event['title'],
                    description: $event['description'],
                    payload: $event['payload'] ?? [],
                );
            }

            return true;
        }, attempts: 3);
    }

    public function claimEvaluation(Assessment $assessment, ?int $expectedTeamId): ?ClaimedAssessmentWork
    {
        return DB::transaction(function () use ($assessment, $expectedTeamId): ?ClaimedAssessmentWork {
            $locked = $this->lockAssessmentForTeam($assessment, $expectedTeamId);

            if ($locked === null || ! $this->evaluationIsClaimable($locked)) {
                return null;
            }

            $attemptId = (string) Str::uuid();

            $locked->update([
                'status' => AssessmentStatus::Evaluating,
                'evaluation_attempt_id' => $attemptId,
                'evaluation_started_at' => now(),
            ]);

            $this->events->record(
                assessment: $locked,
                type: 'evaluation_started',
                title: __('Assessment evaluation started'),
                description: __('The queued AI evaluation job started processing answers.'),
            );

            return new ClaimedAssessmentWork($locked->fresh(['campaign']) ?? $locked, $attemptId);
        }, attempts: 3);
    }

    public function finalizeEvaluation(
        Assessment $assessment,
        string $attemptId,
        AssessmentEvaluationOutcome $outcome,
    ): ?Assessment {
        return DB::transaction(function () use ($assessment, $attemptId, $outcome): ?Assessment {
            $locked = $this->lockAssessmentForTeam($assessment, $assessment->campaign?->team_id);

            if ($locked === null
                || ! $locked->status->isEvaluationProcessing()
                || $locked->evaluation_attempt_id !== $attemptId) {
                return null;
            }

            $locked->update([
                ...$outcome->attributes,
                'evaluation_attempt_id' => null,
                'evaluation_started_at' => null,
            ]);

            foreach ($outcome->events as $event) {
                $this->events->record(
                    assessment: $locked,
                    type: $event['type'],
                    title: $event['title'],
                    description: $event['description'],
                    payload: $event['payload'] ?? [],
                );
            }

            return $locked->fresh();
        }, attempts: 3);
    }

    /**
     * @return ClaimedAssessmentWork|true|null Claimed work, true when incomplete Approved was marked failed, or null when no-op.
     */
    public function claimEmailDelivery(Assessment $assessment, ?int $expectedTeamId): ClaimedAssessmentWork|true|null
    {
        return DB::transaction(function () use ($assessment, $expectedTeamId): ClaimedAssessmentWork|true|null {
            $locked = $this->lockAssessmentForTeam($assessment, $expectedTeamId, ['user', 'campaign.team']);

            if ($locked === null) {
                return null;
            }

            if ($locked->status !== AssessmentStatus::Approved) {
                return null;
            }

            if (! $this->assessmentHasCompleteDeliveryData($locked)) {
                $locked->update([
                    'status' => AssessmentStatus::EmailFailed,
                    'email_delivery_attempt_id' => null,
                    'email_delivery_started_at' => null,
                ]);

                $this->events->record(
                    assessment: $locked,
                    type: 'email_failed',
                    title: __('Interview email skipped'),
                    description: __('Interview email could not be sent because the assessment is not sendable.'),
                    payload: [
                        'from_status' => AssessmentStatus::Approved->value,
                        'to_status' => AssessmentStatus::EmailFailed->value,
                    ],
                );

                return true;
            }

            $attemptId = (string) Str::uuid();

            $locked->update([
                'status' => AssessmentStatus::EmailSending,
                'email_delivery_attempt_id' => $attemptId,
                'email_delivery_started_at' => now(),
            ]);

            return new ClaimedAssessmentWork($locked->fresh(['user', 'campaign.team']) ?? $locked, $attemptId);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{type: string, title: string, description: string, payload?: array<string, mixed>}>  $events
     */
    public function finalizeEmailDelivery(
        Assessment $assessment,
        string $attemptId,
        array $attributes,
        array $events,
    ): bool {
        return DB::transaction(function () use ($assessment, $attemptId, $attributes, $events): bool {
            $locked = $this->lockAssessmentForTeam($assessment, $assessment->campaign?->team_id, ['user', 'campaign.team']);

            if ($locked === null
                || $locked->status !== AssessmentStatus::EmailSending
                || $locked->email_delivery_attempt_id !== $attemptId) {
                return false;
            }

            $locked->update([
                ...$attributes,
                'email_delivery_attempt_id' => null,
                'email_delivery_started_at' => null,
            ]);

            foreach ($events as $event) {
                $this->events->record(
                    assessment: $locked,
                    type: $event['type'],
                    title: $event['title'],
                    description: $event['description'],
                    payload: $event['payload'] ?? [],
                );
            }

            return true;
        }, attempts: 3);
    }

    public function abandonStaleEmailDelivery(Assessment $assessment, ?int $expectedTeamId): bool
    {
        return DB::transaction(function () use ($assessment, $expectedTeamId): bool {
            $locked = $this->lockAssessmentForTeam($assessment, $expectedTeamId, ['user', 'campaign.team']);

            if ($locked === null
                || $locked->status !== AssessmentStatus::EmailSending
                || ! $this->attemptIsStale(
                    $locked->email_delivery_attempt_id,
                    $locked->email_delivery_started_at,
                )) {
                return false;
            }

            $attemptId = $locked->email_delivery_attempt_id;

            $locked->update([
                'status' => AssessmentStatus::EmailFailed,
                'email_delivery_attempt_id' => null,
                'email_delivery_started_at' => null,
            ]);

            $this->events->record(
                assessment: $locked,
                type: 'email_failed',
                title: __('Interview email outcome unknown'),
                description: __('Interview email delivery was interrupted and must be retried manually.'),
                payload: [
                    'outcome' => 'unknown',
                    'attempt_id' => $attemptId,
                    'requires_manual_retry' => true,
                ],
            );

            return true;
        }, attempts: 3);
    }

    /**
     * @param  list<string>  $with
     */
    private function lockAssessmentForTeam(Assessment $assessment, ?int $expectedTeamId, array $with = ['campaign.team']): ?Assessment
    {
        $assessment->loadMissing('campaign');

        $teamId = $expectedTeamId ?? $assessment->campaign?->team_id;

        if ($teamId === null) {
            return null;
        }

        $team = Team::query()->whereKey($teamId)->lockForUpdate()->first();

        if ($team === null || $team->status !== TeamStatus::Active) {
            return null;
        }

        $locked = Assessment::query()
            ->whereKey($assessment->id)
            ->lockForUpdate()
            ->with($with)
            ->first();

        if ($locked === null || $locked->campaign?->team_id !== $team->id) {
            return null;
        }

        return $locked;
    }

    private function assessmentHasCompleteDeliveryData(Assessment $assessment): bool
    {
        return filled($assessment->approved_email_subject)
            && filled($assessment->approved_email_body)
            && filled($assessment->user?->email);
    }

    private function resumeScreeningIsClaimable(Assessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::Submitted
            || ($assessment->status === AssessmentStatus::ResumeProcessing
                && $this->attemptIsStale(
                    $assessment->resume_screening_attempt_id,
                    $assessment->resume_screening_started_at,
                ));
    }

    private function evaluationIsClaimable(Assessment $assessment): bool
    {
        return $assessment->status->isEvaluationClaimable()
            || ($assessment->status->isEvaluationProcessing()
                && $this->attemptIsStale(
                    $assessment->evaluation_attempt_id,
                    $assessment->evaluation_started_at,
                ));
    }

    private function attemptIsStale(?string $attemptId, ?CarbonInterface $startedAt): bool
    {
        $staleAfter = max(1, (int) config('assessment.queue.external_work_stale_after'));

        return filled($attemptId) && $startedAt?->lte(now()->subSeconds($staleAfter));
    }
}
