<?php

namespace App\Services;

use App\CampaignStatus;
use App\ExamSessionStatus;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\Team;
use App\Models\User;
use App\TeamStatus;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignLifecycleService
{
    /**
     * Whether any Candidate invitation, exam session, or assessment exists for the campaign.
     */
    public function hasCandidateActivity(Campaign $campaign): bool
    {
        return $campaign->invitations()->exists()
            || $campaign->examSessions()->exists()
            || $campaign->assessments()->exists();
    }

    /**
     * Reject definition mutations once candidates have been invited or have activity.
     */
    public function assertDefinitionEditable(Campaign $campaign): void
    {
        if (! $this->hasCandidateActivity($campaign)) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __('This campaign definition is frozen because candidates have already been invited. Clone it as a new draft to make changes.'),
        ]);
    }

    /**
     * Run a definition mutation while serializing it with candidate activity and cloning.
     *
     * @template TValue
     *
     * @param  Closure(Campaign): TValue  $mutation
     * @return TValue
     */
    public function withEditableDefinition(Campaign $campaign, Closure $mutation): mixed
    {
        return DB::transaction(function () use ($campaign, $mutation): mixed {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $this->assertDefinitionEditable($lockedCampaign);

            return $mutation($lockedCampaign);
        });
    }

    /**
     * Allow archive only when no exam session is still in progress.
     */
    public function assertCanArchive(Campaign $campaign): void
    {
        if (! $campaign->examSessions()->where('status', ExamSessionStatus::InProgress)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __('This campaign cannot be archived while an exam is in progress.'),
        ]);
    }

    public function archive(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $this->assertCanArchive($lockedCampaign);
            $lockedCampaign->update([
                'status' => CampaignStatus::Archived,
                'activated_at' => null,
            ]);

            return $lockedCampaign;
        });
    }

    /**
     * Clone candidate-facing definition into a new same-Team draft without history.
     */
    public function cloneToDraft(Campaign $source, User $actor): Campaign
    {
        return DB::transaction(function () use ($source, $actor): Campaign {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $lockedSource = Campaign::query()
                ->whereKey($source->id)
                ->lockForUpdate()
                ->firstOrFail();
            $team = Team::query()->whereKey($lockedSource->team_id)->lockForUpdate()->firstOrFail();

            $this->assertActorCanClone($team, $user);

            if ($lockedSource->team_id !== $team->id) {
                throw ValidationException::withMessages([
                    'campaign' => __('Campaigns can only be cloned within the same Team.'),
                ]);
            }

            $lockedSource->load([
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ]);

            $clone = Campaign::query()->create([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'title' => $lockedSource->title.' (Copy)',
                'role_title' => $lockedSource->role_title,
                'seniority' => $lockedSource->seniority,
                'job_description' => $lockedSource->job_description,
                'required_skills' => $lockedSource->required_skills,
                'language' => $lockedSource->language,
                'threshold_score' => $lockedSource->threshold_score,
                'ranking_weights' => $lockedSource->ranking_weights,
                'status' => CampaignStatus::Draft,
                'ai_generation_audit' => null,
                'activated_at' => null,
            ]);

            foreach ($lockedSource->sections as $section) {
                $clonedSection = CampaignSection::query()->create([
                    'campaign_id' => $clone->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'duration_minutes' => $section->duration_minutes,
                    'scoring_mode' => $section->scoring_mode,
                    'weight' => $section->weight,
                    'sort_order' => $section->sort_order,
                ]);

                foreach ($section->questions as $question) {
                    CampaignQuestion::query()->create([
                        'campaign_id' => $clone->id,
                        'campaign_section_id' => $clonedSection->id,
                        'type' => $question->type,
                        'grading_mode' => $question->grading_mode,
                        'prompt' => $question->prompt,
                        'options' => $question->options,
                        'correct_answer' => $question->correct_answer,
                        'expected_rubric' => $question->expected_rubric,
                        'points' => $question->points,
                        'difficulty' => $question->difficulty,
                        'skill_tags' => $question->skill_tags,
                        'ai_generated' => $question->ai_generated,
                        'status' => $question->status,
                        'is_required' => $question->is_required,
                        'sort_order' => $question->sort_order,
                    ]);
                }
            }

            return $clone;
        });
    }

    private function assertActorCanClone(Team $team, User $user): void
    {
        if ($user->current_team_id !== $team->id) {
            throw ValidationException::withMessages([
                'campaign' => __('Select this Campaign\'s Team as your Current Team before cloning.'),
            ]);
        }

        $membership = $user->activeTeamMemberships()
            ->where('team_id', $team->id)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'campaign' => __('You must be an active member of this Team to clone the Campaign.'),
            ]);
        }

        if ($team->status !== TeamStatus::Active) {
            throw ValidationException::withMessages([
                'campaign' => __('The Current Team is deactivated and cannot clone Campaigns.'),
            ]);
        }
    }
}
