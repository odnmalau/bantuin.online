<?php

namespace App\Services\Ai;

use App\Ai\Agents\ResumeScreeningAgent;
use App\Models\Assessment;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;

class QwenResumeScreener
{
    use ConfiguresQwenAssessmentAgent;

    /**
     * Screen an assessment resume with Qwen.
     */
    public function screen(Assessment $assessment): ResumeScreeningResult
    {
        $assessment->loadMissing('user', 'campaign');

        $this->assertQwenApiKeyConfigured(ResumeScreeningException::class);

        $response = $this->promptStructuredAgent(
            new ResumeScreeningAgent,
            $this->prompt($assessment),
            ResumeScreeningException::class,
        );

        return ResumeScreeningResult::fromStructuredOutput($response->toArray());
    }

    /**
     * Build the prompt payload sent to Qwen.
     *
     * @return array<string, mixed>
     */
    public function promptPayload(Assessment $assessment): array
    {
        $campaign = $assessment->campaign;

        return [
            'instruction' => 'Screen the resume against the role context. Output JSON matching the structured schema.',
            'candidate' => [
                'name' => $assessment->user?->name,
                'email' => $assessment->user?->email,
            ],
            'campaign' => [
                'title' => $campaign?->title,
                'role_title' => $campaign?->role_title,
                'seniority' => $campaign?->seniority,
                'job_description' => $campaign?->job_description,
                'required_skills' => $campaign?->required_skills ?? [],
                'nice_to_have_skills' => $campaign?->nice_to_have_skills ?? [],
                'threshold_score' => $campaign?->threshold_score,
            ],
            'assessment_context' => [
                'question_count' => count($assessment->answers_payload ?? []),
                'questions' => collect($assessment->answers_payload ?? [])
                    ->map(fn (array $answer): string => (string) ($answer['question'] ?? ''))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'resume' => [
                'original_name' => $assessment->resume_original_name,
                'text' => $assessment->resume_text,
            ],
            'screening_policy' => [
                'ignore_protected_attributes' => true,
                'protected_attributes' => [
                    'age',
                    'gender',
                    'race',
                    'religion',
                    'nationality',
                    'marital status',
                    'disability',
                    'family status',
                ],
                'human_review_required' => true,
            ],
        ];
    }

    private function prompt(Assessment $assessment): string
    {
        return $this->encodePrompt($this->promptPayload($assessment));
    }
}
