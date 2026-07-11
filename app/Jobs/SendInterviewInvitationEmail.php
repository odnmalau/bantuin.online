<?php

namespace App\Jobs;

use App\AssessmentStatus;
use App\Mail\InterviewInvitationMail;
use App\Models\Assessment;
use App\Models\Team;
use App\Services\AssessmentEventRecorder;
use App\TeamStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInterviewInvitationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public readonly ?int $teamId;

    public function __construct(public Assessment $assessment)
    {
        $teamId = $assessment->campaign()->value('team_id');
        $this->teamId = $teamId === null ? null : (int) $teamId;
    }

    /**
     * Execute the job.
     */
    public function handle(?AssessmentEventRecorder $events = null): void
    {
        DB::transaction(function () use ($events): void {
            if ($this->teamId !== null) {
                Team::query()->whereKey($this->teamId)->lockForUpdate()->firstOrFail();
            }

            $this->send($events);
        }, attempts: 3);
    }

    private function send(?AssessmentEventRecorder $events): void
    {
        $events ??= app(AssessmentEventRecorder::class);
        $assessment = $this->assessment->fresh(['user', 'campaign.team']);

        if ($assessment === null
            || ($this->teamId !== null && ($assessment->campaign?->team_id !== $this->teamId
                || $assessment->campaign->team?->status !== TeamStatus::Active))) {
            return;
        }

        if (! $this->assessmentIsSendable($assessment)) {
            Log::warning('Interview invitation email skipped because assessment is not sendable.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'status' => $assessment->status->value,
            ]);

            $this->markEmailFailed(
                assessment: $assessment,
                events: $events,
                title: __('Interview email skipped'),
                description: __('Interview email could not be sent because the assessment is not sendable.'),
                payload: [
                    'from_status' => $assessment->status->value,
                    'to_status' => AssessmentStatus::EmailFailed->value,
                ],
            );

            return;
        }

        try {
            Mail::to($assessment->user->email)->send(new InterviewInvitationMail(
                subjectLine: $assessment->approved_email_subject,
                body: $assessment->approved_email_body,
            ));
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Interview invitation email failed.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'exception' => $exception::class,
            ]);

            $this->markEmailFailed(
                assessment: $assessment,
                events: $events,
                title: __('Interview email failed'),
                description: __('Interview invitation email failed during delivery.'),
                payload: [
                    'exception' => $exception::class,
                ],
            );

            return;
        }

        $assessment->update([
            'status' => AssessmentStatus::EmailSent,
            'email_sent_at' => now(),
        ]);

        $events->record(
            assessment: $assessment,
            type: 'email_sent',
            title: __('Interview email sent'),
            description: __('Interview invitation email was sent to the candidate.'),
            payload: [
                'recipient' => $assessment->user->email,
                'subject' => $assessment->approved_email_subject,
            ],
        );
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    private function assessmentIsSendable(Assessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::Approved
            && filled($assessment->approved_email_subject)
            && filled($assessment->approved_email_body)
            && filled($assessment->user?->email);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markEmailFailed(
        Assessment $assessment,
        AssessmentEventRecorder $events,
        string $title,
        string $description,
        array $payload,
    ): void {
        $assessment->update([
            'status' => AssessmentStatus::EmailFailed,
        ]);

        $events->record(
            assessment: $assessment,
            type: 'email_failed',
            title: $title,
            description: $description,
            payload: $payload,
        );
    }
}
