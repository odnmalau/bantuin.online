<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Services\Ai\AssessmentCriticResult;
use App\Services\Ai\AssessmentEvaluationResult;
use App\Services\Ai\QwenAssessmentCritic;
use App\Services\Ai\QwenAssessmentEvaluator;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssessmentEvaluationPipeline
{
    public function __construct(
        private QwenAssessmentEvaluator $evaluator,
        private QwenAssessmentCritic $critic,
        private AssessmentThreshold $threshold,
        private DeterministicAssessmentGrader $deterministicGrader,
        private CandidateRankingCalculator $rankingCalculator,
        private AssessmentExternalWorkCoordinator $coordinator,
    ) {}

    public function evaluate(Assessment $assessment): ?Assessment
    {
        $assessment = $assessment->fresh(['campaign']);

        if ($assessment === null
            || ! $assessment->status->isEvaluationProcessing()
            || blank($assessment->evaluation_attempt_id)) {
            return null;
        }

        $attemptId = $assessment->evaluation_attempt_id;
        $outcome = $this->compute($assessment);

        return $this->coordinator->finalizeEvaluation($assessment, $attemptId, $outcome);
    }

    public function compute(Assessment $assessment): AssessmentEvaluationOutcome
    {
        $assessment = $assessment->fresh(['campaign']) ?? $assessment;
        $passingScore = $this->threshold->passingScoreFor($assessment);

        try {
            $result = $this->evaluator->evaluate($assessment);
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Assessment AI evaluation failed.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'exception' => $exception::class,
            ]);

            return AssessmentEvaluationOutcome::failure($exception::class);
        }

        $events = [
            AssessmentEvaluationOutcome::event(
                type: 'qwen_essay_evaluation_completed',
                title: __('Qwen essay evaluation completed'),
                description: __('Qwen returned a validated structured assessment evaluation.'),
                payload: [
                    'score' => $result->score,
                    'email_draft_present' => filled($result->emailSubject) && filled($result->emailBody),
                ],
            ),
        ];

        $deterministicBreakdown = $this->deterministicGrader->breakdown($assessment);
        $mcqScore = $deterministicBreakdown['score'];
        $ranking = $this->rankingCalculator->calculate($assessment, $mcqScore, $result->score, $deterministicBreakdown['section_scores']);
        $reviewScore = $ranking['score'] ?? $result->score;
        $needsManualReview = (bool) $assessment->needs_manual_review;
        $criticPayload = null;
        $criticBlocksAutopilotApproval = false;
        $criticResult = null;

        $events[] = AssessmentEvaluationOutcome::event(
            type: 'deterministic_grading_completed',
            title: __('Deterministic grading completed'),
            description: __('Objective answer snapshots were graded without AI where available.'),
            payload: [
                'mcq_score' => $mcqScore,
                'section_count' => count($deterministicBreakdown['section_scores']),
            ],
        );

        $events[] = AssessmentEvaluationOutcome::event(
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
            $criticResult = $this->critic->review(
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

            $events[] = AssessmentEvaluationOutcome::event(
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

            $events[] = AssessmentEvaluationOutcome::event(
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

        if (filled($emailSubject) && filled($emailBody)) {
            $events[] = AssessmentEvaluationOutcome::event(
                type: 'draft_email_generated',
                title: __('Draft interview email prepared'),
                description: __('A draft interview email is ready for Admin review.'),
                payload: [
                    'subject' => $emailSubject,
                ],
            );
        }

        $events[] = AssessmentEvaluationOutcome::event(
            type: 'evaluation_completed',
            title: __('Assessment evaluation completed'),
            description: __('Assessment scores, ranking, critic result, and review status were saved.'),
            payload: [
                'status' => $status->value,
                'review_score' => $reviewScore,
                'passing_score' => $passingScore,
                'passing_score_source' => $this->threshold->passingScoreSource($assessment),
                'needs_manual_review' => $needsManualReview,
            ],
        );

        return new AssessmentEvaluationOutcome(
            failed: false,
            attributes: [
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
            ],
            events: $events,
            evaluation: $result,
            critic: $criticResult,
        );
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
