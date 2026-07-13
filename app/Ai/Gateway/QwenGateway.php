<?php

namespace App\Ai\Gateway;

use Closure;
use Generator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use LogicException;

class QwenGateway implements TextGateway
{
    private Closure $invokingTool;

    private Closure $invokedTool;

    public function __construct(private readonly HttpFactory $http)
    {
        $this->invokingTool = fn (Tool $tool, array $arguments): null => null;
        $this->invokedTool = fn (Tool $tool, array $arguments, mixed $result): null => null;
    }

    /**
     * Generate text representing the next message in a conversation.
     *
     * @param  array<int, Message>  $messages
     * @param  array<int, Tool>  $tools
     * @param  array<string, Type>|null  $schema
     */
    public function generateText(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        if ($tools !== []) {
            throw new LogicException('Qwen assessment evaluation does not support tool invocation.');
        }

        $response = $this->http
            ->baseUrl($this->baseUrl($provider))
            ->withToken($provider->providerCredentials()['key'])
            ->timeout($timeout ?? $this->timeout($provider))
            ->retry(
                times: (int) config('assessment.qwen.transport_attempt_count', 2),
                sleepMilliseconds: (int) config('assessment.qwen.transport_retry_sleep_ms', 300),
            )
            ->acceptJson()
            ->asJson()
            ->throw()
            ->post('chat/completions', $this->payload($provider, $model, $instructions, $messages, $schema, $options));

        $data = $response->json();
        $text = data_get($data, 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new LogicException('Qwen returned an empty assessment evaluation response.');
        }

        $usage = new Usage(
            promptTokens: (int) data_get($data, 'usage.prompt_tokens', 0),
            completionTokens: (int) data_get($data, 'usage.completion_tokens', 0),
        );

        $meta = new Meta($provider->name(), $model);

        if ($schema === null) {
            return new TextResponse($text, $usage, $meta);
        }

        $structured = json_decode($text, true);

        if (! is_array($structured)) {
            throw new LogicException('Qwen returned invalid structured assessment JSON.');
        }

        return new StructuredTextResponse($structured, $text, $usage, $meta);
    }

    /**
     * Stream text representing the next message in a conversation.
     */
    public function streamText(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): Generator {
        throw new LogicException('Qwen assessment evaluation does not support streaming.');
    }

    /**
     * Specify callbacks that should be invoked when tools are invoking / invoked.
     */
    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        $this->invokingTool = $invoking;
        $this->invokedTool = $invoked;

        return $this;
    }

    /**
     * @param  array<int, Message>  $messages
     * @param  array<string, Type>|null  $schema
     * @return array<string, mixed>
     */
    private function payload(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        ?array $schema,
        ?TextGenerationOptions $options,
    ): array {
        $payload = [
            'model' => $model,
            'messages' => $this->messages($instructions, $messages),
        ];

        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
            $payload['enable_thinking'] = false;
        }

        $providerOptions = $options?->providerOptions($provider->driver()) ?? [];
        $payload = array_merge($payload, $providerOptions);

        if ($schema !== null) {
            $payload['enable_thinking'] = false;
        }

        return $payload;
    }

    /**
     * @param  array<int, Message>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(?string $instructions, array $messages): array
    {
        return collect([
            ...($instructions === null || $instructions === '' ? [] : [
                ['role' => 'system', 'content' => $instructions],
            ]),
            ...collect($messages)
                ->map(fn (Message $message): array => [
                    'role' => $message->role->value === 'assistant' ? 'assistant' : 'user',
                    'content' => $message->content ?? '',
                ])
                ->all(),
        ])->values()->all();
    }

    private function baseUrl(TextProvider $provider): string
    {
        return rtrim($this->provider($provider)->additionalConfiguration()['url'], '/');
    }

    private function timeout(TextProvider $provider): int
    {
        return (int) ($this->provider($provider)->additionalConfiguration()['timeout'] ?? 30);
    }

    private function provider(TextProvider $provider): Provider
    {
        if (! $provider instanceof Provider) {
            throw new LogicException('Qwen provider must extend Laravel AI provider.');
        }

        return $provider;
    }
}
