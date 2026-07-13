<?php

namespace App\Jobs;

use App\AssessmentStatus;
use App\Mail\InterviewInvitationMail;
use App\Models\Assessment;
use App\Services\AssessmentEvaluationOutcome;
use App\Services\AssessmentExternalWorkCoordinator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class SendInterviewInvitationEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    public readonly ?int $teamId;

    public function __construct(public Assessment $assessment)
    {
        $teamId = $assessment->campaign()->value('team_id');
        $this->teamId = $teamId === null ? null : (int) $teamId;
    }

    public function uniqueId(): string
    {
        return 'interview-email-'.$this->assessment->id;
    }

    /**
     * Execute the job.
     */
    public function handle(?AssessmentExternalWorkCoordinator $coordinator = null): void
    {
        $coordinator ??= app(AssessmentExternalWorkCoordinator::class);
        $claimed = $coordinator->claimEmailDelivery($this->assessment, $this->teamId);

        if ($claimed === true) {
            return;
        }

        if ($claimed === null) {
            $assessment = $this->assessment->fresh();

            if ($assessment?->status === AssessmentStatus::EmailSending) {
                $coordinator->abandonStaleEmailDelivery($this->assessment, $this->teamId);

                return;
            }

            if ($assessment !== null) {
                Log::warning('Interview invitation email skipped because assessment is not sendable.', [
                    'assessment_id' => $assessment->id,
                    'candidate_id' => $assessment->user_id,
                    'status' => $assessment->status->value,
                ]);
            }

            return;
        }

        $assessment = $claimed->assessment;

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

            $coordinator->finalizeEmailDelivery(
                assessment: $assessment,
                attemptId: $claimed->attemptId,
                attributes: [
                    'status' => AssessmentStatus::EmailFailed,
                ],
                events: [
                    AssessmentEvaluationOutcome::event(
                        type: 'email_failed',
                        title: __('Interview email failed'),
                        description: __('Interview invitation email failed during delivery.'),
                        payload: [
                            'exception' => $exception::class,
                        ],
                    ),
                ],
            );

            return;
        }

        $finalized = $coordinator->finalizeEmailDelivery(
            assessment: $assessment,
            attemptId: $claimed->attemptId,
            attributes: [
                'status' => AssessmentStatus::EmailSent,
                'email_sent_at' => now(),
            ],
            events: [
                AssessmentEvaluationOutcome::event(
                    type: 'email_sent',
                    title: __('Interview email sent'),
                    description: __('Interview invitation email was sent to the candidate.'),
                    payload: [
                        'recipient' => $assessment->user->email,
                        'subject' => $assessment->approved_email_subject,
                    ],
                ),
            ],
        );

        if (! $finalized) {
            throw new LogicException('Interview invitation email was sent but could not be finalized.');
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }
}
