<?php

namespace Database\Factories;

use App\CampaignInvitationStatus;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignInvitation>
 */
class CampaignInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = fake()->regexify('[A-Za-z0-9]{64}');

        return [
            'campaign_id' => Campaign::factory(),
            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'token_hash' => hash('sha256', $plainToken),
            'invited_by' => fn (array $attributes): int => Campaign::query()
                ->findOrFail($attributes['campaign_id'])
                ->created_by,
            'sent_at' => now(),
            'accepted_at' => null,
            'expires_at' => now()->addDays(14),
            'status' => CampaignInvitationStatus::Pending,
        ];
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            $email = $user?->email ?? $attributes['email'];

            return [
                'email' => $email,
                'user_id' => $user?->id,
                'status' => CampaignInvitationStatus::Accepted,
                'accepted_at' => now(),
            ];
        });
    }

    public function forCandidate(User $user): static
    {
        return $this->state(fn (): array => [
            'email' => $user->email,
        ]);
    }

    /**
     * @return array{invitation: CampaignInvitation, plain_token: string}
     */
    public function createWithPlainToken(array $attributes = []): array
    {
        /** @var CampaignInvitation $invitation */
        $invitation = $this->create($attributes);

        return CampaignInvitation::issueToken($invitation->fresh());
    }
}
