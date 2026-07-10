<?php

namespace Database\Factories;

use App\Models\PlatformOperatorAuthority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformOperatorAuthority>
 */
class PlatformOperatorAuthorityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'granted_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'granted_at' => now()->subWeek(),
            'revoked_at' => now(),
        ]);
    }
}
