<?php

namespace App\Services\Ai\Concerns;

use App\Services\Ai\AssessmentGenerationException;
use Illuminate\Support\Arr;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

trait ConfiguresQwenAssessmentAgent
{
    protected function qwenAgentProvider(): string
    {
        return (string) config('assessment.qwen.provider');
    }

    protected function qwenAgentModel(): string
    {
        return (string) config('assessment.qwen.model');
    }

    protected function qwenReasonerModel(): string
    {
        return (string) config('assessment.qwen.reasoner_model');
    }

    protected function qwenStructuredModel(): string
    {
        return (string) config('assessment.qwen.structured_model');
    }

    protected function qwenAgentTimeout(): int
    {
        return (int) config('assessment.qwen.timeout');
    }

    protected function qwenReasonerTimeout(): int
    {
        return (int) config('assessment.qwen.reasoner_timeout');
    }

    protected function qwenApiKeyConfigured(): bool
    {
        return filled(config('ai.providers.qwen.key'));
    }

    /**
     * @param  class-string<RuntimeException>  $exceptionClass
     */
    protected function assertQwenApiKeyConfigured(string $exceptionClass = AssessmentGenerationException::class): void
    {
        if (! $this->qwenApiKeyConfigured()) {
            throw new $exceptionClass('Qwen API key is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encodePrompt(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  class-string  $exceptionClass
     */
    protected function assertStructuredAgentResponse(mixed $response, string $exceptionClass): StructuredAgentResponse
    {
        if (! $response instanceof StructuredAgentResponse) {
            throw $exceptionClass::invalidOutput('expected structured agent response.');
        }

        return $response;
    }

    /**
     * @param  class-string  $exceptionClass
     */
    protected function assertAgentResponse(mixed $response, string $exceptionClass): AgentResponse
    {
        if (! $response instanceof AgentResponse || trim($response->text) === '') {
            throw $exceptionClass::invalidOutput('expected non-empty reasoning response.');
        }

        return $response;
    }

    /**
     * @param  class-string<RuntimeException>  $exceptionClass
     */
    protected function promptStructuredAgent(
        object $agent,
        string $prompt,
        string $exceptionClass,
        ?string $model = null,
        ?int $timeout = null,
    ): StructuredAgentResponse {
        return $this->assertStructuredAgentResponse(
            $agent->prompt(
                prompt: $prompt,
                provider: $this->qwenAgentProvider(),
                model: $model ?? $this->qwenAgentModel(),
                timeout: $timeout ?? $this->qwenAgentTimeout(),
            ),
            $exceptionClass,
        );
    }

    /**
     * @param  class-string<RuntimeException>  $exceptionClass
     */
    protected function promptReasoningAgent(object $agent, string $prompt, string $exceptionClass): string
    {
        return $this->assertAgentResponse(
            $agent->prompt(
                prompt: $prompt,
                provider: $this->qwenAgentProvider(),
                model: $this->qwenReasonerModel(),
                timeout: $this->qwenReasonerTimeout(),
            ),
            $exceptionClass,
        )->text;
    }

    /**
     * @param  class-string  $agentClass
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     * @return array{
     *     generated_at: string,
     *     provider: string,
     *     model: string,
     *     prompt_version: string,
     *     agent: string,
     *     generation_options: array<string, mixed>
     * }
     */
    protected function generationAuditBase(string $agentClass, array $options): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'provider' => $this->qwenAgentProvider(),
            'model' => $this->qwenStructuredModel(),
            'prompt_version' => (string) config('assessment.generator.prompt_version'),
            'agent' => $agentClass,
            'generation_options' => [
                'question_count' => Arr::get($options, 'question_count'),
                'language' => Arr::get($options, 'language'),
                'difficulty' => Arr::get($options, 'difficulty'),
                'question_mix' => Arr::get($options, 'question_mix'),
            ],
        ];
    }
}
