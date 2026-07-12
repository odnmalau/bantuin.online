<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use Illuminate\Support\Collection;

class CandidateExamPage
{
    public function __construct(
        private ExamSessionService $examSessions,
        private AssessmentSubmissionBuilder $submissionBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, ?Campaign $campaign): array
    {
        if ($campaign === null) {
            return [
                'state' => 'no_campaign',
                'campaign' => null,
            ];
        }

        $assessment = $this->currentAssessment($user, $campaign);

        if ($assessment !== null) {
            return [
                'state' => 'submitted',
                'campaign' => $this->campaignSummary($campaign),
                'assessment' => $this->assessmentSummary($assessment),
            ];
        }

        $sections = $this->examSections($campaign);
        $sectionSummaries = $this->sectionSummaries($sections);

        if ($sections->isEmpty()) {
            return [
                'state' => 'no_campaign',
                'campaign' => null,
            ];
        }

        $session = $this->examSessions->findActiveSession($user, $campaign);

        if ($session === null) {
            return [
                'state' => 'ready_to_start',
                'campaign' => $this->campaignSummary($campaign),
                'sections' => $sectionSummaries,
            ];
        }

        $examSession = $this->examSessions->sessionPayloadForInertia($session, $campaign);

        $assessment = $this->currentAssessment($user, $campaign);

        if ($assessment !== null) {
            return [
                'state' => 'submitted',
                'campaign' => $this->campaignSummary($campaign),
                'assessment' => $this->assessmentSummary($assessment),
            ];
        }

        $session = $session->fresh();

        if ($examSession['ready_to_finalize'] === true) {
            return [
                'state' => 'ready_to_finalize',
                'campaign' => $this->campaignSummary($campaign),
                'sections' => $sectionSummaries,
                'examSession' => $examSession,
            ];
        }

        if ($session->current_section_id !== null) {
            $activeSection = $sections->firstWhere('id', $session->current_section_id);

            if ($activeSection !== null) {
                $currentSection = $this->sectionForCandidate($activeSection);

                return [
                    'state' => 'active_section',
                    'campaign' => $this->campaignSummary($campaign),
                    'sections' => $sectionSummaries,
                    'currentSection' => $currentSection,
                    'questions' => $currentSection['questions'],
                    'examSession' => $examSession,
                ];
            }
        }

        return [
            'state' => 'no_campaign',
            'campaign' => null,
        ];
    }

    private function currentAssessment(User $user, Campaign $campaign): ?Assessment
    {
        return $user
            ->assessments()
            ->whereBelongsTo($campaign)
            ->latest()
            ->first();
    }

    /**
     * @return Collection<int, CampaignSection>
     */
    private function examSections(Campaign $campaign): Collection
    {
        return $this->examSessions->orderedExamSections($campaign);
    }

    /**
     * @param  Collection<int, CampaignSection>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function sectionSummaries(Collection $sections): array
    {
        return $sections
            ->map(fn (CampaignSection $section): array => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'duration_minutes' => $section->duration_minutes,
                'sort_order' => $section->sort_order,
                'question_count' => $section->questions->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignSummary(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'role_title' => $campaign->role_title,
            'team' => [
                'name' => $campaign->team->name,
            ],
            'seniority' => $campaign->seniority,
            'threshold_score' => $campaign->threshold_score,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentSummary(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'created_at' => $assessment->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionForCandidate(CampaignSection $section): array
    {
        $questions = $section->questions
            ->map(fn (CampaignQuestion $question): array => $this->submissionBuilder->questionForCandidate($question))
            ->values()
            ->all();

        return [
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'duration_minutes' => $section->duration_minutes,
            'sort_order' => $section->sort_order,
            'question_count' => count($questions),
            'questions' => $questions,
        ];
    }
}
