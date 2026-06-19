<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentEvent>
 */
class AssessmentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'actor_id' => null,
            'type' => 'system',
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'payload' => null,
            'occurred_at' => now(),
        ];
    }
}
