<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Services\Ai\AssessmentCriticResult;
use App\Services\Ai\AssessmentEvaluationResult;

final readonly class AssessmentEvaluationOutcome
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{type: string, title: string, description: string, payload?: array<string, mixed>}>  $events
     */
    public function __construct(
        public bool $failed,
        public array $attributes,
        public array $events,
        public ?AssessmentEvaluationResult $evaluation = null,
        public ?AssessmentCriticResult $critic = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, title: string, description: string, payload?: array<string, mixed>}
     */
    public static function event(string $type, string $title, string $description, array $payload = []): array
    {
        $event = [
            'type' => $type,
            'title' => $title,
            'description' => $description,
        ];

        if ($payload !== []) {
            $event['payload'] = $payload;
        }

        return $event;
    }

    public static function failure(string $exceptionClass): self
    {
        return new self(
            failed: true,
            attributes: [
                'status' => AssessmentStatus::EvaluationFailed,
            ],
            events: [
                self::event(
                    type: 'evaluation_failed',
                    title: __('Assessment evaluation failed'),
                    description: __('The AI evaluator failed and the assessment needs retry or manual follow-up.'),
                    payload: [
                        'exception' => $exceptionClass,
                    ],
                ),
            ],
        );
    }
}
