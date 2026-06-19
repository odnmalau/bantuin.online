<?php

namespace Database\Factories;

use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->admin(),
            'title' => fake()->jobTitle().' Hiring Campaign',
            'role_title' => fake()->jobTitle(),
            'seniority' => fake()->randomElement(['junior', 'mid', 'senior']),
            'job_description' => fake()->paragraphs(3, true),
            'required_skills' => ['Laravel', 'PostgreSQL', 'Queues'],
            'threshold_score' => 75,
            'status' => CampaignStatus::Draft,
            'ai_generation_notes' => null,
            'activated_at' => null,
        ];
    }

    /**
     * Indicate that the campaign is active.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Active,
            'activated_at' => now(),
        ]);
    }
}
