<?php

namespace Database\Factories;

use App\ExamSessionStatus;
use App\Models\Campaign;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory()->active(),
            'assessment_id' => null,
            'status' => ExamSessionStatus::InProgress,
            'current_section_id' => null,
            'current_section_started_at' => now(),
            'current_section_expires_at' => null,
            'completed_section_ids' => [],
            'warning_count' => 0,
            'integrity_events' => [],
            'answer_drafts' => [],
            'resume_path' => null,
            'resume_original_name' => null,
            'submission_reason' => null,
            'finalized_at' => null,
        ];
    }
}
