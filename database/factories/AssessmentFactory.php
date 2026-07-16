<?php

namespace Database\Factories;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory(),
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => fake()->sentence(),
                    'rubric' => fake()->paragraph(),
                    'answer' => fake()->paragraph(),
                ],
            ],
            'resume_path' => null,
            'resume_original_name' => null,
            'resume_text' => null,
            'resume_score' => null,
            'resume_justification' => null,
            'resume_payload' => null,
            'assessment_score' => null,
            'ranking_score' => null,
            'ranking_payload' => null,
            'critic_payload' => null,
            'needs_manual_review' => false,
            'evaluation_payload' => null,
            'ai_justification' => null,
            'ai_email_subject' => null,
            'ai_email_body' => null,
            'approved_email_subject' => null,
            'approved_email_body' => null,
            'status' => AssessmentStatus::Submitted,
            'evaluated_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'email_sent_at' => null,
        ];
    }

    /**
     * Indicate that the assessment has been approved and is ready to email.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => AssessmentStatus::Approved,
            'approved_email_subject' => 'Final interview invitation',
            'approved_email_body' => 'Final email body from Admin.',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }
}
