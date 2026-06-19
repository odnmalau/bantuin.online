<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ResumeScreeningAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You are an HR technical resume screening agent.

Screen the candidate resume text against the supplied role, job description, and required skills.
Return valid JSON only. Do not include markdown, code fences, or prose outside the JSON object.

Rules:
- Evaluate only job-related skills, technical experience, project evidence, and role fit.
- Ignore protected attributes and inferred demographics, including age, gender, race, religion, nationality, marital status, disability, and family status.
- Do not make a hiring decision. Provide screening evidence for human review.
- If the resume text is sparse or extraction appears incomplete, lower confidence and add a risk flag.
PROMPT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resume_score' => $schema->integer()
                ->min(0)
                ->max(100)
                ->required(),
            'summary' => $schema->string()
                ->required(),
            'matched_skills' => $schema->array()
                ->items($schema->string())
                ->required(),
            'missing_skills' => $schema->array()
                ->items($schema->string())
                ->required(),
            'risk_flags' => $schema->array()
                ->items($schema->string())
                ->required(),
            'interview_probes' => $schema->array()
                ->items($schema->string())
                ->required(),
            'confidence' => $schema->integer()
                ->min(0)
                ->max(100)
                ->required(),
            'justification' => $schema->string()
                ->required(),
        ];
    }
}
