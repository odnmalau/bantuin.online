<?php

namespace App\Http\Controllers\Admin;

use App\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignSection;
use App\Models\Team;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\CampaignInvitationService;
use App\TeamStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['all', ...array_column(CampaignStatus::selectOptions(), 'value')])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $currentTeamId = $request->user()->current_team_id;

        return Inertia::render('admin/campaigns/index', [
            'campaigns' => Inertia::defer(fn () => Campaign::query()
                ->where('team_id', $currentTeamId)
                ->with('creator:id,name,email')
                ->withCount(['sections', 'questions', 'assessments'])
                ->when($search !== '', fn (Builder $query) => $this->applyCampaignSearch($query, $search))
                ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'role_title' => $campaign->role_title,
                    'seniority' => $campaign->seniority,
                    'language' => $campaign->language,
                    'threshold_score' => $campaign->threshold_score,
                    'status' => $campaign->status->value,
                    'status_label' => $campaign->status->label(),
                    'sections_count' => $campaign->sections_count,
                    'questions_count' => $campaign->questions_count,
                    'assessments_count' => $campaign->assessments_count,
                    'created_by' => $campaign->creator?->name,
                    'created_at' => $campaign->created_at,
                ])),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => CampaignStatus::selectOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/campaigns/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request): Campaign {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $currentTeamId = $user->current_team_id;
            $team = $currentTeamId === null
                ? null
                : Team::query()->whereKey($currentTeamId)->lockForUpdate()->first();
            $membership = $team === null
                ? null
                : $user->activeTeamMemberships()
                    ->where('team_id', $team->id)
                    ->lockForUpdate()
                    ->first();

            if ($team === null || $membership === null) {
                throw ValidationException::withMessages([
                    'campaign' => __('Select a Current Team before creating a Campaign.'),
                ]);
            }

            if ($team->status !== TeamStatus::Active) {
                throw ValidationException::withMessages([
                    'campaign' => __('The Current Team is deactivated and cannot create Campaigns.'),
                ]);
            }

            $validated = $request->validated();

            $campaign = Campaign::query()->create([
                ...$validated,
                'team_id' => $team->id,
                'created_by' => $user->id,
                'ranking_weights' => Campaign::defaultRankingWeights(),
                'status' => CampaignStatus::Draft,
                'activated_at' => null,
            ]);

            $this->createDefaultSection($campaign);

            return $campaign;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Display the specified resource.
     */
    public function show(Campaign $campaign): Response
    {
        return Inertia::render('admin/campaigns/show', [
            'campaign' => Inertia::defer(function () use ($campaign): array {
                $campaign->loadMissing([
                    'creator:id,name,email',
                    'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                    'sections.questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                ]);

                $publishability = $this->publishability($campaign);

                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'role_title' => $campaign->role_title,
                    'seniority' => $campaign->seniority,
                    'job_description' => $campaign->job_description,
                    'required_skills' => $campaign->required_skills ?? [],
                    'language' => $campaign->language,
                    'threshold_score' => $campaign->threshold_score,
                    'ranking_weights' => $campaign->resolvedRankingWeights(),
                    'ranking_weights_configured' => $campaign->hasConfiguredRankingWeights(),
                    'status' => $campaign->status->value,
                    'status_label' => $campaign->status->label(),
                    'ai_generation_audit' => $campaign->ai_generation_audit ?? [],
                    'created_by' => $campaign->creator?->name,
                    'created_at' => $campaign->created_at,
                    'activated_at' => $campaign->activated_at,
                    'draft_questions_count' => $publishability['draft_questions_count'],
                    'approved_questions_count' => $publishability['approved_questions_count'],
                    'can_publish' => $publishability['can_publish'],
                    'sections' => $campaign->sections->map(fn (CampaignSection $section): array => [
                        'id' => $section->id,
                        'title' => $section->title,
                        'description' => $section->description,
                        'duration_minutes' => $section->duration_minutes,
                        'scoring_mode' => $section->scoring_mode,
                        'weight' => $section->weight,
                        'sort_order' => $section->sort_order,
                        'questions' => $section->questions
                            ->map(fn ($question): array => $this->campaignQuestionPayload($question)),
                    ]),
                ];
            }),
            'invitations' => Inertia::defer(function () use ($campaign): array {
                $campaign->loadMissing([
                    'invitations' => fn ($query) => $query->latest()->latest('id'),
                ]);

                $invitations = app(CampaignInvitationService::class);

                return $campaign->invitations
                    ->map(fn (CampaignInvitation $invitation): array => $invitations->invitationPayload($invitation))
                    ->all();
            }),
            'questionTypes' => QuestionType::selectOptions(),
            'gradingModeOptions' => QuestionGradingMode::selectOptions(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign): Response
    {
        return Inertia::render('admin/campaigns/edit', [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'role_title' => $campaign->role_title,
                'seniority' => $campaign->seniority,
                'job_description' => $campaign->job_description,
                'required_skills' => $campaign->required_skills ?? [],
                'language' => $campaign->language,
                'threshold_score' => $campaign->threshold_score,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validated();

        $campaign->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        if ($campaign->assessments()->exists()) {
            throw ValidationException::withMessages([
                'campaign' => __('Campaigns with submitted assessments cannot be deleted. Archive the campaign instead.'),
            ]);
        }

        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('admin.campaigns.index');
    }

    /**
     * Publish a campaign after all draft questions have been reviewed.
     */
    public function publish(Campaign $campaign): RedirectResponse
    {
        $this->ensurePublishable($campaign);

        $campaign->update([
            'status' => CampaignStatus::Active,
            'activated_at' => $campaign->activated_at ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign published.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    private function applyCampaignSearch(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query
                ->whereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(role_title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(seniority, \'\')) LIKE ?', [$term]);
        });
    }

    /**
     * Ensure a campaign is ready to become active.
     */
    private function ensurePublishable(Campaign $campaign): void
    {
        $publishability = $this->publishability($campaign);

        if ($publishability['error_message'] === null) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __($publishability['error_message']),
        ]);
    }

    private function createDefaultSection(Campaign $campaign): void
    {
        CampaignSection::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Knowledge Check',
            'description' => 'Initial section for generated or manually-added screening questions.',
            'duration_minutes' => 30,
            'scoring_mode' => 'weighted',
            'weight' => 100,
            'sort_order' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignQuestionPayload(mixed $question): array
    {
        return [
            'id' => $question->id,
            'campaign_section_id' => $question->campaign_section_id,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'grading_mode' => $question->grading_mode->value,
            'grading_mode_label' => $question->grading_mode->label(),
            'prompt' => $question->prompt,
            'options' => $question->options ?? [],
            'correct_answer' => $question->correct_answer ?? [],
            'expected_rubric' => $question->expected_rubric,
            'points' => $question->points,
            'difficulty' => $question->difficulty,
            'skill_tags' => $question->skill_tags ?? [],
            'ai_generated' => $question->ai_generated,
            'status' => $question->status->value,
            'status_label' => $question->status->label(),
            'is_required' => $question->is_required,
            'sort_order' => $question->sort_order,
        ];
    }

    /**
     * @return array{
     *     draft_questions_count: int,
     *     approved_questions_count: int,
     *     can_publish: bool,
     *     error_message: string|null
     * }
     */
    private function publishability(Campaign $campaign): array
    {
        if ($campaign->relationLoaded('sections')) {
            $questions = $campaign->sections->flatMap->questions;
            $draftQuestionsCount = $questions->where('status', QuestionStatus::Draft)->count();
            $approvedQuestionsCount = $questions->where('status', QuestionStatus::Approved)->count();
        } else {
            $draftQuestionsCount = $campaign->questions()
                ->where('status', QuestionStatus::Draft->value)
                ->count();
            $approvedQuestionsCount = $campaign->questions()
                ->where('status', QuestionStatus::Approved->value)
                ->count();
        }

        $errorMessage = match (true) {
            $campaign->status === CampaignStatus::Archived => 'Archived campaigns cannot be published.',
            $draftQuestionsCount > 0 => 'Approve or remove draft questions before publishing this campaign.',
            $approvedQuestionsCount === 0 => 'Add at least one approved question before publishing this campaign.',
            default => null,
        };

        return [
            'draft_questions_count' => $draftQuestionsCount,
            'approved_questions_count' => $approvedQuestionsCount,
            'can_publish' => $errorMessage === null,
            'error_message' => $errorMessage,
        ];
    }
}
