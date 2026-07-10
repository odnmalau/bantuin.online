<?php

namespace App\Http\Controllers\Admin;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
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
            ? collect()
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
     * @return Collection<int, array{
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
    private function rankingsForCampaign(int $campaignId, string $search, string $status, string $dateRange): Collection
    {
        $rankedAssessments = Assessment::query()
            ->with([
                'campaign:id,title,role_title',
                'user:id,name,email',
            ])
            ->where('campaign_id', $campaignId)
            ->whereNotNull('ranking_score')
            ->orderByDesc('ranking_score')
            ->latest()
            ->get()
            ->values()
            ->map(fn (Assessment $assessment, int $index): array => [
                'assessment' => $assessment,
                'rank' => $index + 1,
            ]);

        return $rankedAssessments
            ->when($search !== '', fn (Collection $rankings) => $this->filterBySearch($rankings, $search))
            ->when($status !== 'all', fn (Collection $rankings) => $rankings
                ->filter(fn (array $row): bool => $row['assessment']->status->value === $status)
                ->values())
            ->when($dateRange !== 'all', fn (Collection $rankings) => $this->filterByDateRange($rankings, $dateRange))
            ->map(fn (array $row): array => $this->toRankingRow($row['assessment'], $row['rank']))
            ->values();
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

    /**
     * @param  Collection<int, array{assessment: Assessment, rank: int}>  $rankings
     * @return Collection<int, array{assessment: Assessment, rank: int}>
     */
    private function filterBySearch(Collection $rankings, string $search): Collection
    {
        $term = mb_strtolower($search);

        return $rankings
            ->filter(function (array $row) use ($term): bool {
                $assessment = $row['assessment'];
                $haystacks = [
                    $assessment->user?->name,
                    $assessment->user?->email,
                    $assessment->campaign?->role_title,
                    $assessment->campaign?->title,
                ];

                foreach ($haystacks as $haystack) {
                    if (filled($haystack) && str_contains(mb_strtolower((string) $haystack), $term)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{assessment: Assessment, rank: int}>  $rankings
     * @return Collection<int, array{assessment: Assessment, rank: int}>
     */
    private function filterByDateRange(Collection $rankings, string $dateRange): Collection
    {
        $now = now();

        [$from, $to] = match ($dateRange) {
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [null, null],
        };

        if (! $from instanceof CarbonInterface || ! $to instanceof CarbonInterface) {
            return $rankings;
        }

        return $rankings
            ->filter(function (array $row) use ($from, $to): bool {
                $evaluatedAt = $row['assessment']->evaluated_at;

                return $evaluatedAt instanceof CarbonInterface
                    && $evaluatedAt->betweenIncluded($from, $to);
            })
            ->values();
    }
}
