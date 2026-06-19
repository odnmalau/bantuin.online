<?php

namespace App\Ai\Agents;

use App\QuestionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class QuestionBankGeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You are an HR technical assessment designer building reusable question libraries.

Create new reusable hiring questions from the supplied library context.
Return valid JSON only. Do not include markdown, code fences, or prose outside the JSON object.

Rules:
- Use only the allowed question types listed in the input.
- Prefer practical, skill-specific questions over generic trivia.
- Do not duplicate or lightly rephrase questions listed in existing_questions.
- Multiple choice questions must include plausible options and exactly one correct answer.
- Yes/no, fill blank, and matching pairs questions must include a correct_answer array.
- Short text and long text questions must include an expected_rubric suitable for AI grading.
- Generated output is a draft for admin review; do not mark anything as final or approved.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $questionSchema = $schema->object([
            'type' => $schema->string()
                ->enum(QuestionType::values())
                ->required(),
            'prompt' => $schema->string()
                ->required(),
            'options' => $schema->array()
                ->items($schema->string())
                ->nullable(),
            'correct_answer' => $schema->array()
                ->items($schema->string())
                ->nullable(),
            'expected_rubric' => $schema->string()
                ->nullable(),
            'points' => $schema->integer()
                ->min(1)
                ->max(1000)
                ->required(),
            'difficulty' => $schema->string()
                ->enum(['easy', 'medium', 'hard'])
                ->required(),
            'skill_tags' => $schema->array()
                ->items($schema->string())
                ->nullable(),
            'sort_order' => $schema->integer()
                ->min(0)
                ->required(),
        ])->withoutAdditionalProperties();

        return [
            'questions' => $schema->array()
                ->items($questionSchema)
                ->required(),
        ];
    }
}
