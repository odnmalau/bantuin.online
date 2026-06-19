<?php

namespace Database\Factories;

use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBank>
 */
class QuestionBankFactory extends Factory
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
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'skill_area' => fake()->randomElement(['Laravel', 'PostgreSQL', 'System Design']),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'is_active' => true,
        ];
    }
}
