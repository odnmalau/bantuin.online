<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionGradingMode;
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
            'source_bank_question_id' => null,
            'type' => QuestionType::LongText,
            'grading_mode' => QuestionGradingMode::Ai,
            'prompt' => fake()->sentence(12),
            'options' => null,
            'correct_answer' => null,
            'expected_rubric' => fake()->paragraph(),
            'points' => 10,
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'skill_tags' => ['Laravel'],
            'ai_generated' => false,
            'status' => QuestionStatus::Approved,
            'is_required' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the question is multiple choice.
     */
    public function multipleChoice(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::MultipleChoice,
            'grading_mode' => QuestionGradingMode::Deterministic,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_answer' => ['A'],
            'expected_rubric' => null,
        ]);
    }
}
