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
                type: 'qwen_assessment_evaluation_completed',
                title: __('Qwen assessment evaluation completed'),
                description: __('Qwen returned a validated structured assessment evaluation.'),
                payload: [
                    'score' => $result->score,
                    'confidence' => $result->confidence,
                    'email_draft_present' => filled($result->emailSubject) && filled($result->emailBody),
                ],
            ),
        ];

        $ranking = $this->rankingCalculator->calculate($assessment, $result->score);
        $reviewScore = $ranking['score'] ?? $result->score;
        $result = $this->withResolvedEmailDraft($assessment, $result, $reviewScore, $passingScore);
        $events[0]['payload']['email_draft_present'] = filled($result->emailSubject) && filled($result->emailBody);
        $minimumConfidence = max(0, min(100, (int) config('assessment.evaluation.minimum_confidence', 70)));
        $reviewMargin = max(0, (int) config('assessment.evaluation.manual_review_margin', 3));
        $reviewReasons = [];

        if ($assessment->needs_manual_review) {
            $reviewReasons[] = 'resume_screening_flag';
        }

        if ($result->hasConfidenceBelow($minimumConfidence)) {
            $reviewReasons[] = 'low_confidence';
        }

        if (abs($reviewScore - $passingScore) <= $reviewMargin) {
            $reviewReasons[] = 'borderline_score';
        }

        $needsManualReview = $reviewReasons !== [];
        $criticPayload = null;
        $criticBlocksAutopilotApproval = false;
        $criticResult = null;

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
                ranking: $ranking,
                reviewScore: $reviewScore,
                passingScore: $passingScore,
            );

            $criticPayload = $criticResult->payload();
            $criticBlocksAutopilotApproval = $criticResult->blocksAutopilotApproval();
            $needsManualReview = $needsManualReview || $criticBlocksAutopilotApproval;

            if ($criticBlocksAutopilotApproval) {
                $reviewReasons[] = 'critic_flag';
            }

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
            $reviewReasons[] = 'critic_failed';
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
            $needsManualReview || $criticBlocksAutopilotApproval,
        );

        if ($status === AssessmentStatus::Approved) {
            $events[] = AssessmentEvaluationOutcome::event(
                type: 'autopilot_approved',
                title: __('Assessment approved automatically'),
                description: __('The assessment passed the score, confidence, and critic quality gates.'),
                payload: [
                    'review_score' => $reviewScore,
                    'confidence' => $result->confidence,
                ],
            );
        }

        if (filled($emailSubject) && filled($emailBody)) {
            $events[] = AssessmentEvaluationOutcome::event(
                type: 'draft_email_generated',
                title: __('Draft interview email prepared'),
                description: $status === AssessmentStatus::Approved
                    ? __('A safe interview email was prepared for automatic delivery.')
                    : __('A draft interview email is available for exception review.'),
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
                'manual_review_reasons' => array_values(array_unique($reviewReasons)),
            ],
        );

        return new AssessmentEvaluationOutcome(
            failed: false,
            attributes: [
                'assessment_score' => $result->score,
                'evaluation_payload' => [
                    ...$result->payload(),
                    'manual_review_reasons' => array_values(array_unique($reviewReasons)),
                ],
                'ranking_score' => $ranking['score'],
                'ranking_payload' => $ranking['payload'],
                'critic_payload' => $criticPayload,
                'needs_manual_review' => $needsManualReview,
                'ai_justification' => $result->justification,
                'ai_email_subject' => $emailSubject,
                'ai_email_body' => $emailBody,
                'approved_email_subject' => $status === AssessmentStatus::Approved ? $emailSubject : null,
                'approved_email_body' => $status === AssessmentStatus::Approved ? $emailBody : null,
                'approved_at' => $status === AssessmentStatus::Approved ? now() : null,
                'approved_by' => null,
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

    private function withResolvedEmailDraft(
        Assessment $assessment,
        AssessmentEvaluationResult $evaluation,
        int $reviewScore,
        int $passingScore,
    ): AssessmentEvaluationResult {
        if ($reviewScore < $passingScore) {
            return $evaluation->withEmailDraft(null, null);
        }

        if (filled($evaluation->emailSubject) && filled($evaluation->emailBody)) {
            return $evaluation;
        }

        $roleTitle = trim((string) ($assessment->campaign?->role_title ?? 'the role'));

        return $evaluation->withEmailDraft(
            subject: __('Interview Invitation - :role', ['role' => $roleTitle]),
            body: __('Thank you for completing the assessment. We would like to invite you to continue to the interview stage. Our team will contact you with the next steps.'),
        );
    }

    private function resolveStatusAfterEvaluation(
        int $reviewScore,
        int $passingScore,
        bool $needsManualReview,
    ): AssessmentStatus {
        if ($needsManualReview) {
            return AssessmentStatus::NeedsManualReview;
        }

        if ($reviewScore >= $passingScore) {
            return AssessmentStatus::Approved;
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
