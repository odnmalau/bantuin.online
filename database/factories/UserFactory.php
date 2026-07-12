<?php

namespace Database\Factories;

use App\Models\PlatformOperatorAuthority;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'google_id' => 'factory-'.Str::uuid()->toString(),
            'avatar' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function teamOwner(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->current_team_id !== null) {
                return;
            }

            $team = Team::factory()->ownedBy($user)->create();

            $user->selectCurrentTeam($team);
        });
    }

    public function teamAdministrator(Team $team): static
    {
        return $this->afterCreating(function (User $user) use ($team): void {
            TeamMembership::factory()->for($team)->for($user)->administrator()->create();
        });
    }

    public function teamCollaborator(Team $team): static
    {
        return $this->afterCreating(function (User $user) use ($team): void {
            TeamMembership::factory()->for($team)->for($user)->collaborator()->create();
        });
    }

    public function withCurrentTeam(Team $team): static
    {
        return $this->afterCreating(function (User $user) use ($team): void {
            $user->selectCurrentTeam($team);
        });
    }

    public function platformOperator(): static
    {
        return $this->afterCreating(function (User $user): void {
            PlatformOperatorAuthority::factory()->for($user)->create();
        });
    }

    /**
     * Indicate that the user authenticates with a specific Google account id.
     */
    public function withGoogleId(?string $googleId = null): static
    {
        return $this->state(fn () => [
            'google_id' => $googleId ?? 'factory-'.Str::uuid()->toString(),
        ]);
    }
}
