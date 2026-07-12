<?php

namespace App\Jobs;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Services\Ai\QwenResumeScreener;
use App\Services\AssessmentEvaluationOutcome;
use App\Services\AssessmentExternalWorkCoordinator;
use App\Services\ResumeTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScreenResumeWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public readonly ?int $teamId;

    public function __construct(public Assessment $assessment)
    {
        $teamId = $assessment->campaign()->value('team_id');
        $this->teamId = $teamId === null ? null : (int) $teamId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        ResumeTextExtractor $extractor,
        QwenResumeScreener $screener,
        AssessmentExternalWorkCoordinator $coordinator,
    ): void {
        $claimed = $coordinator->claimResumeScreening($this->assessment, $this->teamId);

        if ($claimed === null) {
            return;
        }

        $assessment = $claimed->assessment;

        try {
            $resumeText = $extractor->extract($assessment->resume_path);

            $assessment->resume_text = $resumeText;
            $result = $screener->screen($assessment);
            $needsManualReview = $result->confidence < 50 || ! empty($result->riskFlags);

            $coordinator->finalizeResumeScreening(
                assessment: $assessment,
                attemptId: $claimed->attemptId,
                attributes: [
                    'resume_text' => $resumeText,
                    'resume_score' => $result->score,
                    'resume_justification' => $result->justification,
                    'resume_payload' => $result->payload(),
                    'needs_manual_review' => $needsManualReview,
                    'status' => AssessmentStatus::Submitted,
                ],
                events: [
                    AssessmentEvaluationOutcome::event(
                        type: 'resume_extracted',
                        title: __('Resume text extracted'),
                        description: __('Resume text extraction completed.'),
                        payload: [
                            'character_count' => mb_strlen($resumeText),
                            'has_text' => filled($resumeText),
                        ],
                    ),
                    AssessmentEvaluationOutcome::event(
                        type: 'resume_screened',
                        title: __('Resume screened by AI'),
                        description: __('Qwen resume screening completed.'),
                        payload: [
                            'resume_score' => $result->score,
                            'confidence' => $result->confidence,
                            'matched_skills_count' => count($result->matchedSkills),
                            'missing_skills_count' => count($result->missingSkills),
                            'risk_flags_count' => count($result->riskFlags),
                            'needs_manual_review' => $needsManualReview,
                        ],
                    ),
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Resume AI screening failed.', [
                'assessment_id' => $assessment->id,
                'candidate_id' => $assessment->user_id,
                'exception' => $exception::class,
            ]);

            $coordinator->finalizeResumeScreening(
                assessment: $assessment,
                attemptId: $claimed->attemptId,
                attributes: [
                    'resume_justification' => __('Resume screening failed and needs manual review.'),
                    'needs_manual_review' => true,
                    'status' => AssessmentStatus::Submitted,
                ],
                events: [
                    AssessmentEvaluationOutcome::event(
                        type: 'resume_screening_failed',
                        title: __('Resume screening failed'),
                        description: __('Resume screening failed and requires manual review.'),
                        payload: [
                            'exception' => $exception::class,
                        ],
                    ),
                ],
            );
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
