<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentEvent;
use App\Models\User;
use DateTimeInterface;
use Stringable;

class AssessmentEventRecorder
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'authorization',
        'password',
        'secret',
        'token',
        'prompt',
        'instructions',
        'messages',
        'original_context',
        'invalid_output',
        'api_key',
        'apikey',
        'qwen_key',
        'dashscope_key',
    ];

    /**
     * Record an assessment timeline event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Assessment $assessment,
        string $type,
        string $title,
        ?string $description = null,
        array $payload = [],
        ?User $actor = null,
    ): AssessmentEvent {
        return $assessment->events()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'payload' => $this->sanitizePayload($payload),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function sanitizePayload(array $payload): ?array
    {
        $sanitized = $this->sanitizeArray($payload);

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $payload): array
    {
        return collect($payload)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [
                $key => $this->sanitizeValue((string) $key, $value),
            ])
            ->all();
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = str($key)->lower()->toString();

        return array_any(
            self::SENSITIVE_KEY_FRAGMENTS,
            fn (string $fragment): bool => str_contains($normalizedKey, $fragment),
        );
    }
}
