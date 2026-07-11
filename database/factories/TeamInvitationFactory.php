<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'role' => TeamMembershipRole::Collaborator,
            'invited_by' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => TeamInvitationStatus::Pending,
            'expires_at' => now()->addDays(14),
            'accepted_at' => null,
            'accepted_by' => null,
            'revoked_at' => null,
        ];
    }
}
