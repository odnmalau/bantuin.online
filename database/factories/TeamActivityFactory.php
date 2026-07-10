<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamActivity>
 */
class TeamActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'actor_id' => null,
            'actor_context' => 'system',
            'action' => 'team.created',
            'subject_type' => 'team',
            'subject_id' => null,
            'before_state' => null,
            'after_state' => null,
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
