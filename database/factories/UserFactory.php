<?php

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
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
            'role' => UserRole::Candidate,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the user is a candidate.
     */
    public function candidate(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Candidate,
        ]);
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
