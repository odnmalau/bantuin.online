<?php

namespace App\Services\Ai;

use App\Ai\Agents\McqOptionsRegeneratorAgent;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\QuestionType;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;
use App\Services\Ai\Concerns\ValidatesMcqStructuredOutput;

class QwenMcqOptionsRegenerator
{
    use ConfiguresQwenAssessmentAgent;
    use ValidatesMcqStructuredOutput;

    public function regenerateForCampaignQuestion(CampaignQuestion $question, Campaign $campaign): McqOptionsRegenerationResult
    {
        $this->assertRegeneratableMcq($question);

        return $this->regenerate($this->promptPayload(
            prompt: $question->prompt,
            difficulty: $question->difficulty,
            skillTags: $question->skill_tags ?? [],
            currentOptions: $question->options ?? [],
            context: [
                'source' => 'campaign_question',
                'campaign' => [
                    'title' => $campaign->title,
                    'role_title' => $campaign->role_title,
                    'seniority' => $campaign->seniority,
                    'language' => $campaign->language,
                    'job_description' => $campaign->job_description,
                    'required_skills' => $campaign->required_skills ?? [],
                ],
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function regenerate(array $payload): McqOptionsRegenerationResult
    {
        $this->assertQwenApiKeyConfigured();

        $response = $this->promptStructuredAgent(
            new McqOptionsRegeneratorAgent,
            $this->encodePrompt($payload),
            AssessmentGenerationException::class,
        );

        $validated = $this->validatedMcqOutput($response->toArray());

        return new McqOptionsRegenerationResult(
            options: $validated['options'],
            correctAnswer: $validated['correct_answer'],
        );
    }

    /**
     * @param  array<int, string>  $skillTags
     * @param  array<int, string>  $currentOptions
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function promptPayload(
        string $prompt,
        string $difficulty,
        array $skillTags,
        array $currentOptions,
        array $context,
    ): array {
        return [
            'instruction' => 'Regenerate multiple choice options for the question below. Output JSON matching the structured schema.',
            'question' => [
                'prompt' => $prompt,
                'difficulty' => $difficulty,
                'skill_tags' => $skillTags,
                'current_options' => $currentOptions,
            ],
            'context' => $context,
            'prompt_version' => (string) config('assessment.generator.prompt_version'),
        ];
    }

    private function assertRegeneratableMcq(CampaignQuestion $question): void
    {
        if ($question->type !== QuestionType::MultipleChoice) {
            throw AssessmentGenerationException::invalidOutput('only multiple choice questions support option regeneration.');
        }
    }
}
