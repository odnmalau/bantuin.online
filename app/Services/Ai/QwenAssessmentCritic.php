<?php

namespace App\Services\Ai;

use App\Ai\Agents\AssessmentCriticAgent;
use App\Models\Assessment;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;

class QwenAssessmentCritic
{
    use ConfiguresQwenAssessmentAgent;

    /**
     * Review an evaluated assessment package with Qwen.
     */
    public function review(
        Assessment $assessment,
        AssessmentEvaluationResult $evaluation,
        ?int $mcqScore,
        array $ranking,
        int $reviewScore,
        int $passingScore,
    ): AssessmentCriticResult {
        $assessment->loadMissing('user', 'campaign');

        $this->assertQwenApiKeyConfigured(AssessmentCriticException::class);

        $response = $this->promptStructuredAgent(
            new AssessmentCriticAgent,
            $this->prompt($assessment, $evaluation, $mcqScore, $ranking, $reviewScore, $passingScore),
            AssessmentCriticException::class,
        );

        return AssessmentCriticResult::fromStructuredOutput($response->toArray());
    }

    /**
     * @param  array<string, mixed>  $ranking
     * @return array<string, mixed>
     */
    public function promptPayload(
        Assessment $assessment,
        AssessmentEvaluationResult $evaluation,
        ?int $mcqScore,
        array $ranking,
        int $reviewScore,
        int $passingScore,
    ): array {
        return [
            'instruction' => 'Critic-check this assessment package. Output JSON matching the structured schema.',
            'threshold' => $passingScore,
            'review_score' => $reviewScore,
            'candidate' => [
                'name' => $assessment->user?->name,
                'email' => $assessment->user?->email,
            ],
            'campaign' => [
                'title' => $assessment->campaign?->title,
                'role_title' => $assessment->campaign?->role_title,
                'required_skills' => $assessment->campaign?->required_skills ?? [],
            ],
            'score_components' => [
                'resume_score' => $assessment->resume_score,
                'essay_score' => $evaluation->score,
                'mcq_score' => $mcqScore,
                'ranking_score' => $ranking['score'] ?? null,
                'ranking_payload' => $ranking['payload'] ?? null,
            ],
            'essay_evaluation' => [
                'score' => $evaluation->score,
                'justification' => $evaluation->justification,
            ],
            'resume_screening' => [
                'score' => $assessment->resume_score,
                'justification' => $assessment->resume_justification,
                'payload' => $assessment->resume_payload,
            ],
            'email_draft' => [
                'subject' => $evaluation->emailSubject,
                'body' => $evaluation->emailBody,
            ],
            'policy' => [
                'email_must_be_generic' => true,
                'forbidden_email_claims' => [
                    'specific interview schedule',
                    'interviewer name',
                    'meeting link',
                    'salary',
                    'hiring commitment',
                    'guaranteed job offer',
                ],
                'protected_attributes_to_ignore' => [
                    'age',
                    'gender',
                    'race',
                    'religion',
                    'nationality',
                    'marital status',
                    'disability',
                    'family status',
                    'photo',
                    'detailed address',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ranking
     */
    private function prompt(
        Assessment $assessment,
        AssessmentEvaluationResult $evaluation,
        ?int $mcqScore,
        array $ranking,
        int $reviewScore,
        int $passingScore,
    ): string {
        return $this->encodePrompt(
            $this->promptPayload($assessment, $evaluation, $mcqScore, $ranking, $reviewScore, $passingScore),
        );
    }
}
