<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignSection>
 */
class CampaignSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'title' => fake()->randomElement(['Knowledge Check', 'Technical Reasoning', 'System Design']),
            'description' => fake()->paragraph(),
            'duration_minutes' => fake()->numberBetween(20, 60),
            'weight' => 100,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
