<?php

namespace Database\Factories;

use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamMembership;
use App\OwnershipTransferStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnershipTransfer>
 */
class OwnershipTransferFactory extends Factory
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
            'owner_membership_id' => TeamMembership::factory(),
            'recipient_membership_id' => TeamMembership::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => OwnershipTransferStatus::Pending,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }
}
