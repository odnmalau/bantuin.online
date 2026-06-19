<?php

namespace Database\Seeders;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\User;
use App\QuestionStatus;
use App\UserRole;
use Illuminate\Database\Seeder;

class DemoAssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            DemoCampaignSeeder::class,
        ]);

        $admin = User::query()
            ->where('email', 'admin@hirepilot.test')
            ->firstOrFail();
        $campaign = Campaign::query()
            ->where('title', 'Backend Engineer Autopilot Campaign')
            ->firstOrFail();

        $demoAssessments = [
            [
                'name' => 'Passed Candidate',
                'email' => 'passed@hirepilot.test',
                'status' => AssessmentStatus::PendingApproval,
                'score' => 84,
                'justification' => 'The candidate explains the core technical tradeoffs clearly and gives practical implementation details across database, queue, and debugging topics.',
                'ai_email_subject' => 'Interview Invitation - Passed Candidate',
                'ai_email_body' => "Hello Passed Candidate,\n\nThank you for completing the technical assessment. Based on your assessment result, we would like to invite you to continue to the interview stage.\n\nOur team will contact you with the next steps.\n\nBest regards,\nHR Team",
                'approved' => false,
                'email_sent' => false,
            ],
            [
                'name' => 'Manual Review Candidate',
                'email' => 'review@hirepilot.test',
                'status' => AssessmentStatus::Evaluated,
                'score' => 68,
                'justification' => 'The candidate gives a partially correct answer but misses several details around reliability, measurement, and operational tradeoffs. Admin may still review this as a possible false negative.',
                'ai_email_subject' => null,
                'ai_email_body' => null,
                'approved' => false,
                'email_sent' => false,
            ],
            [
                'name' => 'Invited Candidate',
                'email' => 'invited@hirepilot.test',
                'status' => AssessmentStatus::EmailSent,
                'score' => 91,
                'justification' => 'The candidate demonstrates strong technical reasoning, identifies edge cases, and provides concrete operational steps for reliable implementation.',
                'ai_email_subject' => 'Interview Invitation - Invited Candidate',
                'ai_email_body' => "Hello Invited Candidate,\n\nThank you for completing the technical assessment. Based on your assessment result, we would like to invite you to continue to the interview stage.\n\nOur team will contact you with the next steps.\n\nBest regards,\nHR Team",
                'approved' => true,
                'email_sent' => true,
            ],
        ];

        foreach ($demoAssessments as $demoAssessment) {
            $candidate = User::query()->updateOrCreate(
                ['email' => $demoAssessment['email']],
                [
                    'name' => $demoAssessment['name'],
                    'google_id' => 'seed-'.str($demoAssessment['email'])->before('@'),
                    'role' => UserRole::Candidate,
                ],
            );

            Assessment::query()->updateOrCreate(
                [
                    'user_id' => $candidate->id,
                    'campaign_id' => $campaign->id,
                ],
                $this->assessmentAttributes($demoAssessment, $admin->id, $campaign),
            );
        }
    }

    /**
     * @param  array{name: string, email: string, status: AssessmentStatus, score: int, justification: string, ai_email_subject: ?string, ai_email_body: ?string, approved: bool, email_sent: bool}  $demoAssessment
     * @return array<string, mixed>
     */
    private function assessmentAttributes(array $demoAssessment, int $adminId, Campaign $campaign): array
    {
        $approvedAt = $demoAssessment['approved'] ? now()->subDay() : null;

        return [
            'answers_payload' => $this->answersPayload($campaign, $demoAssessment['name']),
            'ai_score' => $demoAssessment['score'],
            'ai_justification' => $demoAssessment['justification'],
            'ai_email_subject' => $demoAssessment['ai_email_subject'],
            'ai_email_body' => $demoAssessment['ai_email_body'],
            'approved_email_subject' => $demoAssessment['approved'] ? $demoAssessment['ai_email_subject'] : null,
            'approved_email_body' => $demoAssessment['approved'] ? $demoAssessment['ai_email_body'] : null,
            'status' => $demoAssessment['status'],
            'evaluated_at' => now()->subDays(2),
            'approved_by' => $demoAssessment['approved'] ? $adminId : null,
            'approved_at' => $approvedAt,
            'rejected_at' => null,
            'email_sent_at' => $demoAssessment['email_sent'] ? now()->subHours(12) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function answersPayload(Campaign $campaign, string $candidateName): array
    {
        $answers = [
            'database',
            'Yes',
            'I would reproduce the slow endpoint, inspect logs and timings, check database queries for N+1 issues, look at indexes and cache opportunities, then measure the endpoint again after each focused fix.',
            'AI-only scoring can create false negatives or biased outcomes, so a human reviewer should inspect borderline cases before decisions are final.',
        ];

        return CampaignQuestion::query()
            ->with('section')
            ->whereBelongsTo($campaign)
            ->where('status', QuestionStatus::Approved->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take(4)
            ->get()
            ->values()
            ->map(fn (CampaignQuestion $question, int $index): array => [
                'question_id' => $question->id,
                'campaign_question_id' => $question->id,
                'campaign_section_id' => $question->campaign_section_id,
                'section_id' => $question->campaign_section_id,
                'section_title' => $question->section?->title,
                'section_weight' => $question->section?->weight,
                'question' => $question->prompt,
                'rubric' => $question->expected_rubric,
                'type' => $question->type->value,
                'type_label' => $question->type->label(),
                'options' => $question->options ?? [],
                'correct_answer' => $question->correct_answer,
                'points' => $question->points,
                'difficulty' => $question->difficulty,
                'skill_tags' => $question->skill_tags ?? [],
                'answer' => "{$candidateName}: {$answers[$index]}",
            ])
            ->all();
    }
}
