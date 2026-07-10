<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use stdClass;

class RankingOverview
{
    private const ATTENTION_ITEM_LIMIT = 3;

    /**
     * Build the ranking overview summary and chart payloads.
     *
     * @return array{
     *     has_ranked_candidates: bool,
     *     summary: array{
     *         total_ranked: int,
     *         pending_approval: int,
     *         needs_manual_review: int,
     *         average_ranking_score: int|null,
     *         period_label: string,
     *         changes: array{
     *             total_ranked: float|null,
     *             pending_approval: float|null,
     *             needs_manual_review: float|null,
     *             average_ranking_score: float|null
     *         }
     *     },
     *     charts: array{
     *         ranking_activity: list<array{date: string, label: string, ranked_count: int}>,
     *         score_distribution: list<array{bucket: string, label: string, count: int}>
     *     },
     *     needs_attention: array{
     *         summary: array{campaigns: int, pending: int, manual_reviews: int, failures: int},
     *         items: list<array{campaign_id: int, label: string, badge: string}>
     *     }
     * }
     */
    public function build(int $teamId): array
    {
        $periodEnd = now()->endOfDay();
        $periodStart = now()->subDays(6)->startOfDay();
        $previousPeriodEnd = $periodStart->copy()->subSecond();
        $previousPeriodStart = now()->subDays(13)->startOfDay();

        $currentSummary = $this->summaryForPeriod($teamId, $periodStart, $periodEnd);
        $previousSummary = $this->summaryForPeriod($teamId, $previousPeriodStart, $previousPeriodEnd);

        return [
            'has_ranked_candidates' => Assessment::query()
                ->whereHas('campaign', fn ($query) => $query->where('team_id', $teamId))
                ->whereNotNull('ranking_score')
                ->exists(),
            'summary' => [
                ...$currentSummary,
                'period_label' => 'Last 7 days',
                'changes' => [
                    'total_ranked' => $this->changePercent(
                        $currentSummary['total_ranked'],
                        $previousSummary['total_ranked'],
                    ),
                    'average_ranking_score' => $this->changePercent(
                        $currentSummary['average_ranking_score'],
                        $previousSummary['average_ranking_score'],
                    ),
                    'pending_approval' => $this->changePercent(
                        $currentSummary['pending_approval'],
                        $previousSummary['pending_approval'],
                    ),
                    'needs_manual_review' => $this->changePercent(
                        $currentSummary['needs_manual_review'],
                        $previousSummary['needs_manual_review'],
                    ),
                ],
            ],
            'charts' => [
                'ranking_activity' => $this->rankingActivity($teamId, $periodStart, $periodEnd),
                'score_distribution' => $this->scoreDistribution($teamId, $periodStart, $periodEnd),
            ],
            'needs_attention' => $this->needsAttention($teamId),
        ];
    }

    /**
     * @return array{
     *     total_ranked: int,
     *     pending_approval: int,
     *     needs_manual_review: int,
     *     average_ranking_score: int|null
     * }
     */
    private function summaryForPeriod(int $teamId, CarbonInterface $start, CarbonInterface $end): array
    {
        $pending = AssessmentStatus::PendingApproval->value;

        $row = $this->rankedInPeriodQuery($teamId, $start, $end)
            ->toBase()
            ->selectRaw('count(*) as total_ranked')
            ->selectRaw('avg(ranking_score) as average_ranking_score')
            ->selectRaw('count(case when status = ? then 1 end) as pending_approval', [$pending])
            ->selectRaw('count(case when needs_manual_review = ? then 1 end) as needs_manual_review', [true])
            ->first();

        $totalRanked = (int) ($row->total_ranked ?? 0);

        return [
            'total_ranked' => $totalRanked,
            'pending_approval' => (int) ($row->pending_approval ?? 0),
            'needs_manual_review' => (int) ($row->needs_manual_review ?? 0),
            'average_ranking_score' => $totalRanked === 0
                ? null
                : (int) round((float) $row->average_ranking_score),
        ];
    }

    /**
     * @return list<array{date: string, label: string, ranked_count: int}>
     */
    private function rankingActivity(int $teamId, CarbonInterface $start, CarbonInterface $end): array
    {
        $countsByDay = $this->rankedInPeriodQuery($teamId, $start, $end)
            ->toBase()
            ->selectRaw('date(coalesce(evaluated_at, created_at)) as day')
            ->selectRaw('count(*) as ranked_count')
            ->groupBy('day')
            ->pluck('ranked_count', 'day');

        return collect(range(6, 0))
            ->map(function (int $daysAgo) use ($countsByDay): array {
                $day = now()->subDays($daysAgo)->startOfDay();
                $key = $day->toDateString();

                return [
                    'date' => $key,
                    'label' => $day->format('D'),
                    'ranked_count' => (int) ($countsByDay[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{bucket: string, label: string, count: int}>
     */
    private function scoreDistribution(int $teamId, CarbonInterface $start, CarbonInterface $end): array
    {
        $row = $this->rankedInPeriodQuery($teamId, $start, $end)
            ->toBase()
            ->selectRaw('count(case when ranking_score between 0 and 49 then 1 end) as bucket_0_49')
            ->selectRaw('count(case when ranking_score between 50 and 69 then 1 end) as bucket_50_69')
            ->selectRaw('count(case when ranking_score between 70 and 84 then 1 end) as bucket_70_84')
            ->selectRaw('count(case when ranking_score between 85 and 100 then 1 end) as bucket_85_100')
            ->first();

        return [
            ['bucket' => '0-49', 'label' => '0–49', 'count' => (int) ($row->bucket_0_49 ?? 0)],
            ['bucket' => '50-69', 'label' => '50–69', 'count' => (int) ($row->bucket_50_69 ?? 0)],
            ['bucket' => '70-84', 'label' => '70–84', 'count' => (int) ($row->bucket_70_84 ?? 0)],
            ['bucket' => '85-100', 'label' => '85–100', 'count' => (int) ($row->bucket_85_100 ?? 0)],
        ];
    }

    /**
     * @return array{
     *     summary: array{campaigns: int, pending: int, manual_reviews: int, failures: int},
     *     items: list<array{campaign_id: int, label: string, badge: string}>
     * }
     */
    private function needsAttention(int $teamId): array
    {
        $empty = [
            'summary' => [
                'campaigns' => 0,
                'pending' => 0,
                'manual_reviews' => 0,
                'failures' => 0,
            ],
            'items' => [],
        ];

        $pending = AssessmentStatus::PendingApproval->value;
        $evaluationFailed = AssessmentStatus::EvaluationFailed->value;
        $emailFailed = AssessmentStatus::EmailFailed->value;

        $failuresExpression = 'sum(case when status in (?, ?) then 1 else 0 end)';
        $pendingExpression = 'sum(case when status = ? then 1 else 0 end)';
        $manualReviewsExpression = 'sum(case when needs_manual_review = ? then 1 else 0 end)';

        /** @var Collection<int, stdClass> $rows */
        $rows = Assessment::query()
            ->whereHas('campaign', fn ($query) => $query->where('team_id', $teamId))
            ->toBase()
            ->select('campaign_id')
            ->selectRaw("{$failuresExpression} as failures", [$evaluationFailed, $emailFailed])
            ->selectRaw("{$pendingExpression} as pending", [$pending])
            ->selectRaw("{$manualReviewsExpression} as manual_reviews", [true])
            ->groupBy('campaign_id')
            ->havingRaw(
                "{$failuresExpression} > 0 or {$pendingExpression} > 0 or {$manualReviewsExpression} > 0",
                [$evaluationFailed, $emailFailed, $pending, true],
            )
            ->get()
            ->map(function (stdClass $row): array {
                $failures = (int) $row->failures;
                $pendingCount = (int) $row->pending;
                $manualReviews = (int) $row->manual_reviews;

                [$priority, $badge] = match (true) {
                    $failures > 0 => [
                        0,
                        $failures === 1 ? '1 failure' : "{$failures} failures",
                    ],
                    $pendingCount > 0 => [
                        1,
                        $pendingCount === 1 ? '1 pending' : "{$pendingCount} pending",
                    ],
                    default => [
                        2,
                        $manualReviews === 1
                            ? '1 manual review'
                            : "{$manualReviews} manual reviews",
                    ],
                };

                return [
                    'campaign_id' => (int) $row->campaign_id,
                    'priority' => $priority,
                    'weight' => max($failures, $pendingCount, $manualReviews),
                    'badge' => $badge,
                    'pending' => $pendingCount,
                    'manual_reviews' => $manualReviews,
                    'failures' => $failures,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $priority = $left['priority'] <=> $right['priority'];

                if ($priority !== 0) {
                    return $priority;
                }

                return $right['weight'] <=> $left['weight'];
            })
            ->values();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $titles = Campaign::query()
            ->where('team_id', $teamId)
            ->whereIn('id', $rows->pluck('campaign_id'))
            ->pluck('title', 'id');

        return [
            'summary' => [
                'campaigns' => $rows->count(),
                'pending' => (int) $rows->sum('pending'),
                'manual_reviews' => (int) $rows->sum('manual_reviews'),
                'failures' => (int) $rows->sum('failures'),
            ],
            'items' => $rows
                ->take(self::ATTENTION_ITEM_LIMIT)
                ->map(fn (array $row): array => [
                    'campaign_id' => $row['campaign_id'],
                    'label' => (string) ($titles[$row['campaign_id']] ?? 'Campaign #'.$row['campaign_id']),
                    'badge' => $row['badge'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function rankedInPeriodQuery(int $teamId, CarbonInterface $start, CarbonInterface $end)
    {
        return Assessment::query()
            ->whereHas('campaign', fn ($query) => $query->where('team_id', $teamId))
            ->whereNotNull('ranking_score')
            ->whereRaw('coalesce(evaluated_at, created_at) >= ?', [$start])
            ->whereRaw('coalesce(evaluated_at, created_at) <= ?', [$end]);
    }

    private function changePercent(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : 100.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
