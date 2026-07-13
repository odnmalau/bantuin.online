<?php

namespace App\Http\Controllers\Admin;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function dateRangeOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All time'],
            ['value' => '7d', 'label' => 'Last 7 days'],
            ['value' => '30d', 'label' => 'Last 30 days'],
            ['value' => 'this_month', 'label' => 'This month'],
        ];
    }

    /**
     * Show the candidate ranking leaderboard for a campaign.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'campaign' => [
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where('team_id', $request->user()->current_team_id),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['all', ...array_column(AssessmentStatus::selectOptions(), 'value')])],
            'date_range' => ['nullable', 'string', Rule::in(array_column(self::dateRangeOptions(), 'value'))],
        ]);

        $campaignOptions = $this->campaignOptions($request->user()->current_team_id);
        $campaignId = $this->resolveCampaignId(
            isset($filters['campaign']) ? (int) $filters['campaign'] : null,
            $campaignOptions,
        );
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $dateRange = (string) ($filters['date_range'] ?? 'all');

        $rankings = $campaignId === null
            ? $this->emptyRankingsPaginator()
            : $this->rankingsForCampaign($campaignId, $search, $status, $dateRange);

        return Inertia::render('admin/rankings/index', [
            'rankings' => $rankings,
            'filters' => [
                'campaign' => $campaignId === null ? '' : (string) $campaignId,
                'search' => $search,
                'status' => $status,
                'date_range' => $dateRange,
            ],
            'campaignOptions' => $campaignOptions
                ->map(fn (Campaign $campaign): array => [
                    'value' => (string) $campaign->id,
                    'label' => $campaign->title,
                ])
                ->values()
                ->all(),
            'statusOptions' => AssessmentStatus::selectOptions(),
            'dateRangeOptions' => self::dateRangeOptions(),
        ]);
    }

    /**
     * @return Collection<int, Campaign>
     */
    private function campaignOptions(int $teamId): Collection
    {
        return Campaign::query()
            ->where('team_id', $teamId)
            ->whereHas('assessments', fn (Builder $query) => $query->whereNotNull('ranking_score'))
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title']);
    }

    /**
     * @param  Collection<int, Campaign>  $campaignOptions
     */
    private function resolveCampaignId(?int $campaignId, Collection $campaignOptions): ?int
    {
        if ($campaignOptions->isEmpty()) {
            return null;
        }

        if ($campaignId !== null && $campaignOptions->contains('id', $campaignId)) {
            return $campaignId;
        }

        return (int) $campaignOptions->first()->id;
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     rank: int,
     *     assessment_id: int,
     *     candidate_name: string|null,
     *     candidate_email: string|null,
     *     campaign_title: string|null,
     *     role_title: string|null,
     *     ranking_score: int|null,
     *     resume_score: int|null,
     *     essay_score: int|null,
     *     mcq_score: int|null,
     *     status: string,
     *     needs_manual_review: bool,
     *     evaluated_at: string|null
     * }>
     */
    private function rankingsForCampaign(int $campaignId, string $search, string $status, string $dateRange): LengthAwarePaginator
    {
        $assessmentsTable = (new Assessment)->getTable();

        $query = Assessment::query()
            ->with([
                'campaign:id,title,role_title',
                'user:id,name,email',
            ])
            ->where("{$assessmentsTable}.campaign_id", $campaignId)
            ->whereNotNull("{$assessmentsTable}.ranking_score")
            ->select([
                "{$assessmentsTable}.id",
                "{$assessmentsTable}.user_id",
                "{$assessmentsTable}.campaign_id",
                "{$assessmentsTable}.ranking_score",
                "{$assessmentsTable}.resume_score",
                "{$assessmentsTable}.essay_score",
                "{$assessmentsTable}.mcq_score",
                "{$assessmentsTable}.status",
                "{$assessmentsTable}.needs_manual_review",
                "{$assessmentsTable}.evaluated_at",
                "{$assessmentsTable}.created_at",
            ])
            ->selectSub(
                Assessment::query()
                    ->from("{$assessmentsTable} as higher")
                    ->selectRaw('COUNT(*) + 1')
                    ->whereColumn('higher.campaign_id', "{$assessmentsTable}.campaign_id")
                    ->whereNotNull('higher.ranking_score')
                    ->where(function (Builder $query) use ($assessmentsTable): void {
                        $query->whereColumn('higher.ranking_score', '>', "{$assessmentsTable}.ranking_score")
                            ->orWhere(function (Builder $query) use ($assessmentsTable): void {
                                $query->whereColumn('higher.ranking_score', '=', "{$assessmentsTable}.ranking_score")
                                    ->whereColumn('higher.created_at', '>', "{$assessmentsTable}.created_at");
                            })
                            ->orWhere(function (Builder $query) use ($assessmentsTable): void {
                                $query->whereColumn('higher.ranking_score', '=', "{$assessmentsTable}.ranking_score")
                                    ->whereColumn('higher.created_at', '=', "{$assessmentsTable}.created_at")
                                    ->whereColumn('higher.id', '>', "{$assessmentsTable}.id");
                            });
                    }),
                'rank',
            )
            ->when($search !== '', fn (Builder $query) => $this->applySearchFilter($query, $search))
            ->when($status !== 'all', fn (Builder $query) => $query->where("{$assessmentsTable}.status", $status))
            ->when($dateRange !== 'all', fn (Builder $query) => $this->applyDateRangeFilter($query, $dateRange, $assessmentsTable))
            ->orderByDesc("{$assessmentsTable}.ranking_score")
            ->orderByDesc("{$assessmentsTable}.created_at")
            ->orderByDesc("{$assessmentsTable}.id");

        return $query
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Assessment $assessment): array => $this->toRankingRow(
                $assessment,
                (int) $assessment->getAttribute('rank'),
            ));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyRankingsPaginator(): LengthAwarePaginator
    {
        return Assessment::query()
            ->whereRaw('0 = 1')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Assessment $assessment): array => $this->toRankingRow($assessment, 0));
    }

    /**
     * @return array{
     *     rank: int,
     *     assessment_id: int,
     *     candidate_name: string|null,
     *     candidate_email: string|null,
     *     campaign_title: string|null,
     *     role_title: string|null,
     *     ranking_score: int|null,
     *     resume_score: int|null,
     *     essay_score: int|null,
     *     mcq_score: int|null,
     *     status: string,
     *     needs_manual_review: bool,
     *     evaluated_at: string|null
     * }
     */
    private function toRankingRow(Assessment $assessment, int $rank): array
    {
        return [
            'rank' => $rank,
            'assessment_id' => $assessment->id,
            'candidate_name' => $assessment->user?->name,
            'candidate_email' => $assessment->user?->email,
            'campaign_title' => $assessment->campaign?->title,
            'role_title' => $assessment->campaign?->role_title,
            'ranking_score' => $assessment->ranking_score,
            'resume_score' => $assessment->resume_score,
            'essay_score' => $assessment->essay_score,
            'mcq_score' => $assessment->mcq_score,
            'status' => $assessment->status->value,
            'needs_manual_review' => $assessment->needs_manual_review,
            'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
        ];
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $term = '%'.str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            mb_strtolower($search),
        ).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->whereHas('user', function (Builder $userQuery) use ($term): void {
                $userQuery->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term]);
            })->orWhereHas('campaign', function (Builder $campaignQuery) use ($term): void {
                $campaignQuery->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(role_title) LIKE ? ESCAPE '!'", [$term]);
            });
        });
    }

    private function applyDateRangeFilter(Builder $query, string $dateRange, string $assessmentsTable): void
    {
        $now = now();

        [$from, $to] = match ($dateRange) {
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [null, null],
        };

        if (! $from instanceof CarbonInterface || ! $to instanceof CarbonInterface) {
            return;
        }

        $query->whereBetween("{$assessmentsTable}.evaluated_at", [$from, $to]);
    }
}
