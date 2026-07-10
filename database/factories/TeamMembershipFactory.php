<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamMembershipRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => TeamMembershipRole::Collaborator,
            'started_at' => now(),
            'ended_at' => null,
            'last_used_at' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => TeamMembershipRole::Owner]);
    }

    public function administrator(): static
    {
        return $this->state(fn () => ['role' => TeamMembershipRole::Administrator]);
    }

    public function collaborator(): static
    {
        return $this->state(fn () => ['role' => TeamMembershipRole::Collaborator]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subWeek(),
            'ended_at' => now(),
        ]);
    }
}
