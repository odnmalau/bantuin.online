<?php

namespace Database\Seeders;

use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\Models\BankQuestion;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignSection;
use App\Models\QuestionBank;
use App\Models\User;
use App\QuestionStatus;
use App\QuestionType;
use Illuminate\Database\Seeder;

class DemoCampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $admin = User::query()
            ->where('email', 'admin@hirepilot.test')
            ->firstOrFail();

        $questionBank = QuestionBank::query()->updateOrCreate(
            ['title' => 'Laravel Backend - Mid Level'],
            [
                'created_by' => $admin->id,
                'description' => 'Reusable screening questions for backend roles using Laravel, PostgreSQL, and queue workers.',
                'skill_area' => 'Backend Engineering',
                'difficulty' => 'medium',
                'is_active' => true,
            ],
        );

        $bankQuestions = collect([
            [
                'type' => QuestionType::MultipleChoice,
                'prompt' => 'Which queue driver is configured for HirePilot?',
                'options' => ['sync', 'database', 'redis', 'sqs'],
                'correct_answer' => ['database'],
                'expected_rubric' => null,
                'points' => 10,
                'difficulty' => 'easy',
                'skill_tags' => ['Laravel', 'Queue'],
                'sort_order' => 10,
            ],
            [
                'type' => QuestionType::YesNo,
                'prompt' => 'Should candidate assessment status updates use polling instead of WebSockets in this product?',
                'options' => ['Yes', 'No'],
                'correct_answer' => ['Yes'],
                'expected_rubric' => null,
                'points' => 10,
                'difficulty' => 'easy',
                'skill_tags' => ['Architecture', 'Platform'],
                'sort_order' => 20,
            ],
            [
                'type' => QuestionType::LongText,
                'prompt' => 'Explain how you would debug a slow Laravel API endpoint in production.',
                'options' => null,
                'correct_answer' => null,
                'expected_rubric' => 'A strong answer mentions reproducing the issue, checking logs and metrics, inspecting database queries and N+1 problems, reviewing indexes and cache opportunities, and validating the fix with measurements.',
                'points' => 30,
                'difficulty' => 'medium',
                'skill_tags' => ['Debugging', 'Laravel', 'Observability'],
                'sort_order' => 30,
            ],
            [
                'type' => QuestionType::ShortText,
                'prompt' => 'Describe one risk of relying only on AI scoring for hiring decisions.',
                'options' => null,
                'correct_answer' => null,
                'expected_rubric' => 'A strong answer identifies false positives, false negatives, bias, incomplete context, or the need for human-in-the-loop review.',
                'points' => 20,
                'difficulty' => 'medium',
                'skill_tags' => ['AI Safety', 'Human Review'],
                'sort_order' => 40,
            ],
        ])->map(function (array $question) use ($questionBank): BankQuestion {
            return BankQuestion::query()->updateOrCreate(
                [
                    'question_bank_id' => $questionBank->id,
                    'prompt' => $question['prompt'],
                ],
                [
                    ...$question,
                    'ai_generated' => true,
                ],
            );
        });

        $campaign = Campaign::query()->updateOrCreate(
            ['title' => 'Backend Engineer Autopilot Campaign'],
            [
                'created_by' => $admin->id,
                'role_title' => 'Backend Engineer',
                'seniority' => 'Mid-level',
                'job_description' => "Own Laravel APIs, PostgreSQL-backed workflows, queue workers, and assessment automation reliability.\nCandidates should be able to reason through tradeoffs and operational failure modes.",
                'required_skills' => ['Laravel', 'PostgreSQL', 'Queues', 'Debugging'],
                'threshold_score' => 75,
                'status' => CampaignStatus::Active,
                'ai_generation_notes' => 'Prefer practical scenario questions over trivia. Keep human review visible for borderline candidates.',
                'activated_at' => now(),
            ],
        );

        $knowledgeSection = CampaignSection::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'title' => 'Knowledge Check',
            ],
            [
                'description' => 'Auto-graded fundamentals.',
                'duration_minutes' => 15,
                'scoring_mode' => 'weighted',
                'weight' => 40,
                'sort_order' => 10,
            ],
        );

        $reasoningSection = CampaignSection::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'title' => 'Technical Reasoning',
            ],
            [
                'description' => 'AI-assisted text evaluation.',
                'duration_minutes' => 45,
                'scoring_mode' => 'weighted',
                'weight' => 60,
                'sort_order' => 20,
            ],
        );

        foreach ($bankQuestions as $bankQuestion) {
            $section = $bankQuestion->type->usesDeterministicGrading()
                ? $knowledgeSection
                : $reasoningSection;

            $campaign->questions()->updateOrCreate(
                [
                    'campaign_section_id' => $section->id,
                    'prompt' => $bankQuestion->prompt,
                ],
                [
                    'source_bank_question_id' => $bankQuestion->id,
                    'type' => $bankQuestion->type,
                    'options' => $bankQuestion->options,
                    'correct_answer' => $bankQuestion->correct_answer,
                    'expected_rubric' => $bankQuestion->expected_rubric,
                    'points' => $bankQuestion->points,
                    'difficulty' => $bankQuestion->difficulty,
                    'skill_tags' => $bankQuestion->skill_tags,
                    'ai_generated' => true,
                    'status' => QuestionStatus::Approved,
                    'is_required' => true,
                    'sort_order' => $bankQuestion->sort_order,
                ],
            );
        }

        $candidate = User::query()
            ->where('email', 'candidate@hirepilot.test')
            ->first();

        if ($candidate !== null) {
            CampaignInvitation::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'email' => $candidate->email,
                ],
                [
                    'user_id' => $candidate->id,
                    'token_hash' => hash('sha256', 'demo-campaign-invite-token'),
                    'invited_by' => $admin->id,
                    'sent_at' => now(),
                    'accepted_at' => now(),
                    'expires_at' => now()->addDays(14),
                    'status' => CampaignInvitationStatus::Accepted,
                ],
            );
        }
    }
}
