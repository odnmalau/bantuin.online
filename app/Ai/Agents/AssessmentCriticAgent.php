<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class AssessmentCriticAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You are a technical hiring assessment critic.

Review the assessment package for consistency, safety, and human-review readiness.
Return valid JSON only. Do not include markdown, code fences, or prose outside the JSON object.

Rules:
- Validate scores are consistent with the justification and ranking components.
- Validate email draft exists only when the assessment meets the configured threshold.
- The email must be generic and must not include a schedule, date, interviewer, meeting link, salary, or hiring commitment.
- Validate resume screening ignores protected attributes such as age, gender, race, religion, nationality, marital status, disability, family status, photo, or detailed address.
- If a minor email issue can be fixed safely, return outcome "repaired" and provide repaired_email.
- If any serious issue requires human judgment, return outcome "needs_manual_review".
- If the package is unusable or contradictory, return outcome "failed".
- Otherwise return outcome "passed".
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'outcome' => $schema->string()
                ->enum(['passed', 'repaired', 'needs_manual_review', 'failed'])
                ->required(),
            'summary' => $schema->string()
                ->required(),
            'findings' => $schema->array()
                ->items($schema->string())
                ->required(),
            'manual_review_required' => $schema->boolean()
                ->required(),
            'repaired_email' => $schema->object([
                'subject' => $schema->string()
                    ->nullable(),
                'body' => $schema->string()
                    ->nullable(),
            ])->required(),
        ];
    }
}
