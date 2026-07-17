<?php

namespace Database\Factories;

use App\Models\CampaignInvitation;
use App\Models\CandidateApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateApplication>
 */
class CandidateApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $candidate = User::factory()->create();

        return [
            'campaign_invitation_id' => CampaignInvitation::factory()->accepted($candidate),
            'resume_path' => 'resumes/'.fake()->uuid().'.pdf',
            'resume_original_name' => 'resume.pdf',
            'resume_uploaded_at' => now(),
            'locked_at' => null,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'locked_at' => now(),
        ]);
    }
}
