<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\TeamStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function create($attributes = [], ?Model $parent = null): Collection|Model
    {
        return DB::transaction(fn () => parent::create($attributes, $parent));
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
            'status' => TeamStatus::Active,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function ownedBy(User $owner): static
    {
        return $this->state(fn () => ['owner_id' => $owner->id]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => [
            'status' => TeamStatus::Deactivated,
            'deactivated_at' => now(),
        ]);
    }
}
