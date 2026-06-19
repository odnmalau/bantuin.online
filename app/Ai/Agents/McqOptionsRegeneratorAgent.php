<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class McqOptionsRegeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You regenerate multiple choice answer options for a hiring assessment question.

Return valid JSON only. Do not include markdown, code fences, or prose outside the JSON object.

Rules:
- Keep the question prompt meaning unchanged; only supply new plausible distractors and one correct answer.
- Provide at least four options unless the input requests fewer.
- Include exactly one correct answer in correct_answer.
- Every correct_answer value must exactly match one of the options strings.
- Options must be distinct, role-relevant, and free of trick wording.
- Do not reference schedules, private data, or external accounts.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'options' => $schema->array()
                ->items($schema->string())
                ->required(),
            'correct_answer' => $schema->array()
                ->items($schema->string())
                ->required(),
        ];
    }
}
