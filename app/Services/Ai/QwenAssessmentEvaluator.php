<?php

namespace App\Services\Ai;

use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\Models\Assessment;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;
use App\Services\AssessmentThreshold;
use Laravel\Ai\Responses\StructuredAgentResponse;

class QwenAssessmentEvaluator
{
    use ConfiguresQwenAssessmentAgent;

    public function __construct(private AssessmentThreshold $threshold) {}

    /**
     * Evaluate an assessment with Qwen.
     */
    public function evaluate(Assessment $assessment): AssessmentEvaluationResult
    {
        $this->assertQwenApiKeyConfigured(AssessmentEvaluationException::class);

        $passingScore = $this->threshold->passingScoreFor($assessment);
        $maxRepairAttempts = $this->maxRepairAttempts();
        $response = $this->promptAgent($this->prompt($assessment));

        foreach (range(0, $maxRepairAttempts) as $attempt) {
            try {
                return $this->resultFromResponse($response, $passingScore);
            } catch (AssessmentEvaluationException $exception) {
                if ($attempt === $maxRepairAttempts) {
                    throw $exception;
                }

                $response = $this->promptAgent($this->repairPrompt(
                    assessment: $assessment,
                    invalidOutput: $response->toArray(),
                    validationError: $exception->getMessage(),
                ));
            }
        }

        throw new AssessmentEvaluationException('Assessment evaluation could not be completed.');
    }

    /**
     * Build the prompt payload shape that will be sent to Qwen when real integration is enabled.
     *
     * @return array<string, mixed>
     */
    public function promptPayload(Assessment $assessment): array
    {
        $assessment->loadMissing(['campaign']);

        $answers = collect($assessment->answers_payload);

        return [
            'campaign' => $assessment->campaign === null ? null : [
                'title' => $assessment->campaign->title,
                'role_title' => $assessment->campaign->role_title,
                'seniority' => $assessment->campaign->seniority,
                'job_description' => $assessment->campaign->job_description,
                'required_skills' => $assessment->campaign->required_skills ?? [],
            ],
            'threshold' => $this->threshold->passingScoreFor($assessment),
            'questions' => $answers
                ->map(fn (array $answer): array => [
                    'question_id' => $answer['question_id'] ?? null,
                    'question' => $answer['question'] ?? '',
                    'rubric' => $answer['rubric'] ?? '',
                    'type' => $answer['type'] ?? null,
                    'grading_mode' => $answer['grading_mode'] ?? null,
                    'points' => $answer['points'] ?? null,
                    'skill_tags' => $answer['skill_tags'] ?? [],
                ])
                ->values()
                ->all(),
            'untrusted_candidate_data' => [
                'assessment_id' => $assessment->id,
                'answers' => $answers
                    ->map(fn (array $answer): array => [
                        'question_id' => $answer['question_id'] ?? null,
                        'answer' => $answer['answer'] ?? '',
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function prompt(Assessment $assessment): string
    {
        return $this->encodePrompt($this->promptPayload($assessment));
    }

    /**
     * @param  array<string, mixed>  $invalidOutput
     */
    private function repairPrompt(Assessment $assessment, array $invalidOutput, string $validationError): string
    {
        return $this->encodePrompt([
            'instruction' => 'The previous JSON evaluation output failed backend validation. Return corrected JSON only that matches the required schema. Do not include markdown or prose outside JSON.',
            'validation_error' => $validationError,
            'invalid_output' => $invalidOutput,
            'required_schema' => [
                'score' => 'integer 0-100',
                'justification' => 'non-empty string',
                'email' => [
                    'subject' => 'string when score >= threshold, otherwise null',
                    'body' => 'string when score >= threshold, otherwise null',
                ],
            ],
            'threshold' => $this->threshold->passingScoreFor($assessment),
            'original_context' => $this->promptPayload($assessment),
        ]);
    }

    private function promptAgent(string $prompt): StructuredAgentResponse
    {
        return $this->promptStructuredAgent(
            new AssessmentEvaluatorAgent,
            $prompt,
            AssessmentEvaluationException::class,
        );
    }

    private function resultFromResponse(StructuredAgentResponse $response, int $passingScore): AssessmentEvaluationResult
    {
        return AssessmentEvaluationResult::fromStructuredOutput(
            $response->toArray(),
            $passingScore,
        );
    }

    private function maxRepairAttempts(): int
    {
        return max(0, (int) config('assessment.evaluation.repair_attempts', 1));
    }
}
