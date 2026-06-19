<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class TextQuestionToMcqConverterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You convert AI-graded short or long text hiring questions into multiple choice questions.

Return valid JSON only. Do not include markdown, code fences, or prose outside the JSON object.

Rules:
- Preserve the original assessment intent and difficulty.
- Rewrite the prompt into a clear multiple choice stem when needed.
- Provide at least four plausible options unless the input requests fewer.
- Include exactly one correct answer in correct_answer.
- Every correct_answer value must exactly match one of the options strings.
- Options must be distinct and role-relevant.
- Do not reference schedules, private data, or external accounts.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->required(),
            'options' => $schema->array()
                ->items($schema->string())
                ->required(),
            'correct_answer' => $schema->array()
                ->items($schema->string())
                ->required(),
        ];
    }
}
