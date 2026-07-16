<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionStatus;
use App\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignQuestion>
 */
class CampaignQuestionFactory extends Factory
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
            'campaign_section_id' => CampaignSection::factory(),
            'type' => QuestionType::LongText,
            'prompt' => fake()->sentence(12),
            'expected_rubric' => fake()->paragraph(),
            'points' => 10,
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'ai_generated' => false,
            'status' => QuestionStatus::Approved,
            'is_required' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
