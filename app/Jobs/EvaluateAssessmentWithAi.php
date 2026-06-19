<?php

namespace App\Jobs;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Services\Ai\AssessmentCriticResult;
use App\Services\Ai\AssessmentEvaluationResult;
use App\Services\Ai\QwenAssessmentCritic;
use App\Services\Ai\QwenAssessmentEvaluator;
use App\Services\AssessmentEventRecorder;
use App\Services\AssessmentSettings;
use App\Services\CandidateRankingCalculator;
use App\Services\DeterministicAssessmentGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateAssessmentWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Assessment $assessment) {}

    /**
     * Execute the job.
     */
    public function handle(
        QwenAssessmentEvaluator $evaluator,
        QwenAssessmentCritic $critic,
        AssessmentSettings $settings,
        DeterministicAssessmentGrader $deterministicGrader,
        CandidateRankingCalculator $rankingCalculator,
        AssessmentEventRecorder $events,
    ): void {
        $assessment = $this->assessment->fresh(['campaign']);

        if ($assessment === null) {
            return;
        }

        $passingScore = $settings->passingScoreFor($assessment);

        $assessment->update([
            'status' => AssessmentStatus::Evaluating,
        ]);

        $events->record(
            assessment: $assessment,
            type: 'evaluation_started',
            title: __('Assessment evaluation started'),
            description: __('The queued AI evaluation job started processing answers.'),
        );

        try {
            $result = $evaluator->evaluate($assessment);
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Assessment AI evaluation failed.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'exception' => $exception::class,
            ]);

            $assessment->update([
                'status' => AssessmentStatus::EvaluationFailed,
            ]);

            $events->record(
                assessment: $assessment,
                type: 'evaluation_failed',
                title: __('Assessment evaluation failed'),
                description: __('The AI evaluator failed and the assessment needs retry or manual follow-up.'),
                payload: [
                    'exception' => $exception::class,
                ],
            );

            return;
        }

        $events->record(
            assessment: $assessment,
            type: 'qwen_essay_evaluation_completed',
            title: __('Qwen essay evaluation completed'),
            description: __('Qwen returned a validated structured assessment evaluation.'),
            payload: [
                'score' => $result->score,
                'email_draft_present' => filled($result->emailSubject) && filled($result->emailBody),
            ],
        );

        $deterministicBreakdown = $deterministicGrader->breakdown($assessment);
        $mcqScore = $deterministicBreakdown['score'];
        $ranking = $rankingCalculator->calculate($assessment, $mcqScore, $result->score, $deterministicBreakdown['section_scores']);
        $reviewScore = $ranking['score'] ?? $result->score;
        $needsManualReview = (bool) $assessment->needs_manual_review;
        $criticPayload = null;
        $criticBlocksAutopilotApproval = false;
        $criticResult = null;

        $events->record(
            assessment: $assessment,
            type: 'deterministic_grading_completed',
            title: __('Deterministic grading completed'),
            description: __('Objective answer snapshots were graded without AI where available.'),
            payload: [
                'mcq_score' => $mcqScore,
                'section_count' => count($deterministicBreakdown['section_scores']),
            ],
        );

        $events->record(
            assessment: $assessment,
            type: 'ranking_calculated',
            title: __('Candidate ranking calculated'),
            description: __('Backend ranking score was calculated from available score components.'),
            payload: [
                'ranking_score' => $ranking['score'],
                'missing_components' => $ranking['payload']['missing_components'] ?? [],
                'weighting_mode' => $ranking['payload']['weighting_mode'] ?? null,
            ],
        );

        try {
            $criticResult = $critic->review(
                assessment: $assessment,
                evaluation: $result,
                mcqScore: $mcqScore,
                ranking: $ranking,
                reviewScore: $reviewScore,
                passingScore: $passingScore,
            );

            $criticPayload = $criticResult->payload();
            $criticBlocksAutopilotApproval = $criticResult->blocksAutopilotApproval();
            $needsManualReview = $needsManualReview || $criticResult->manualReviewRequired;

            $events->record(
                assessment: $assessment,
                type: 'critic_completed',
                title: __('Critic review completed'),
                description: __('Qwen critic checked the assessment package for consistency and safety.'),
                payload: [
                    'outcome' => $criticResult->outcome,
                    'manual_review_required' => $criticResult->manualReviewRequired,
                    'findings_count' => count($criticResult->findings),
                    'blocks_autopilot_approval' => $criticBlocksAutopilotApproval,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Assessment critic failed.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'exception' => $exception::class,
            ]);

            $needsManualReview = true;
            $criticBlocksAutopilotApproval = true;
            $criticPayload = $this->failedCriticPayload($exception);

            $events->record(
                assessment: $assessment,
                type: 'critic_failed',
                title: __('Critic review failed'),
                description: __('Critic pass failed and the assessment requires manual review.'),
                payload: [
                    'exception' => $exception::class,
                ],
            );
        }

        [$emailSubject, $emailBody] = $this->resolveEmailDraft($result, $criticResult);

        $status = $this->resolveStatusAfterEvaluation(
            $reviewScore,
            $passingScore,
            $criticBlocksAutopilotApproval,
        );

        $assessment->update([
            'ai_score' => $result->score,
            'essay_score' => $result->score,
            'mcq_score' => $mcqScore,
            'ranking_score' => $ranking['score'],
            'ranking_payload' => $ranking['payload'],
            'critic_payload' => $criticPayload,
            'needs_manual_review' => $needsManualReview,
            'ai_justification' => $result->justification,
            'ai_email_subject' => $emailSubject,
            'ai_email_body' => $emailBody,
            'evaluated_at' => now(),
            'status' => $status,
        ]);

        if (filled($emailSubject) && filled($emailBody)) {
            $events->record(
                assessment: $assessment,
                type: 'draft_email_generated',
                title: __('Draft interview email prepared'),
                description: __('A draft interview email is ready for Admin review.'),
                payload: [
                    'subject' => $emailSubject,
                ],
            );
        }

        $events->record(
            assessment: $assessment,
            type: 'evaluation_completed',
            title: __('Assessment evaluation completed'),
            description: __('Assessment scores, ranking, critic result, and review status were saved.'),
            payload: [
                'status' => $status->value,
                'review_score' => $reviewScore,
                'passing_score' => $passingScore,
                'passing_score_source' => $settings->passingScoreSource($assessment),
                'needs_manual_review' => $needsManualReview,
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

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveEmailDraft(
        AssessmentEvaluationResult $evaluation,
        ?AssessmentCriticResult $criticResult,
    ): array {
        if ($criticResult?->outcome === 'repaired') {
            return [$criticResult->repairedEmailSubject, $criticResult->repairedEmailBody];
        }

        return [$evaluation->emailSubject, $evaluation->emailBody];
    }

    private function resolveStatusAfterEvaluation(
        int $reviewScore,
        int $passingScore,
        bool $criticBlocksAutopilotApproval,
    ): AssessmentStatus {
        if ($reviewScore >= $passingScore && ! $criticBlocksAutopilotApproval) {
            return AssessmentStatus::PendingApproval;
        }

        return AssessmentStatus::Evaluated;
    }

    /**
     * @return array<string, mixed>
     */
    private function failedCriticPayload(Throwable $exception): array
    {
        return [
            'outcome' => 'failed',
            'summary' => 'Critic pass failed and requires manual review.',
            'findings' => [$exception->getMessage()],
            'manual_review_required' => true,
            'repaired_email' => [
                'subject' => null,
                'body' => null,
            ],
        ];
    }
}
