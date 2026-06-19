<?php

namespace App\Services\Ai;

use App\Ai\Agents\TextQuestionToMcqConverterAgent;
use App\Models\BankQuestion;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\QuestionBank;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;
use App\Services\Ai\Concerns\ValidatesMcqStructuredOutput;
use Illuminate\Support\Arr;

class QwenTextQuestionToMcqConverter
{
    use ConfiguresQwenAssessmentAgent;
    use ValidatesMcqStructuredOutput;

    public function convertCampaignQuestion(CampaignQuestion $question, Campaign $campaign): TextQuestionToMcqConversionResult
    {
        $this->assertConvertibleTextQuestion($question);

        return $this->convert($this->promptPayload(
            prompt: $question->prompt,
            expectedRubric: $question->expected_rubric,
            difficulty: $question->difficulty,
            skillTags: $question->skill_tags ?? [],
            sourceType: $question->type->value,
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

    public function convertBankQuestion(BankQuestion $question, QuestionBank $questionBank): TextQuestionToMcqConversionResult
    {
        $this->assertConvertibleTextQuestion($question);

        return $this->convert($this->promptPayload(
            prompt: $question->prompt,
            expectedRubric: $question->expected_rubric,
            difficulty: $question->difficulty,
            skillTags: $question->skill_tags ?? [],
            sourceType: $question->type->value,
            context: [
                'source' => 'bank_question',
                'question_bank' => [
                    'title' => $questionBank->title,
                    'skill_area' => $questionBank->skill_area,
                    'difficulty' => $questionBank->difficulty,
                    'description' => $questionBank->description,
                ],
            ],
        ));
    }

    /**
     * @param  array<int, string>  $skillTags
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function promptPayload(
        string $prompt,
        ?string $expectedRubric,
        string $difficulty,
        array $skillTags,
        string $sourceType,
        array $context,
    ): array {
        return [
            'instruction' => 'Convert the text question into a multiple choice question. Output JSON matching the structured schema.',
            'source_question' => [
                'type' => $sourceType,
                'prompt' => $prompt,
                'expected_rubric' => $expectedRubric,
                'difficulty' => $difficulty,
                'skill_tags' => $skillTags,
            ],
            'context' => $context,
            'prompt_version' => (string) config('assessment.generator.prompt_version'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function convert(array $payload): TextQuestionToMcqConversionResult
    {
        $this->assertQwenApiKeyConfigured();

        $response = $this->promptStructuredAgent(
            new TextQuestionToMcqConverterAgent,
            $this->encodePrompt($payload),
            AssessmentGenerationException::class,
        );

        return $this->normalizeOutput($response->toArray());
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function normalizeOutput(array $output): TextQuestionToMcqConversionResult
    {
        $prompt = Arr::get($output, 'prompt');

        if (! is_string($prompt) || trim($prompt) === '') {
            throw AssessmentGenerationException::invalidOutput('prompt must be a non-empty string.');
        }

        $validated = $this->validatedMcqOutput($output);

        return new TextQuestionToMcqConversionResult(
            prompt: trim($prompt),
            options: $validated['options'],
            correctAnswer: $validated['correct_answer'],
        );
    }

    private function assertConvertibleTextQuestion(CampaignQuestion|BankQuestion $question): void
    {
        if (! $question->type->canConvertToMcq()) {
            throw AssessmentGenerationException::invalidOutput('only short text and long text questions can be converted to multiple choice.');
        }
    }
}
