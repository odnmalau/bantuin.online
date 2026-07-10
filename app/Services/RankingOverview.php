<?php

namespace App\Services;

use App\AssessmentStatus;
use App\Models\Assessment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RankingOverview
{
    /**
     * Build the ranking overview summary and chart payloads.
     *
     * @return array{
     *     summary: array{
     *         total_ranked: int,
     *         pending_approval: int,
     *         needs_manual_review: int,
     *         average_ranking_score: int|null,
     *         period_label: string,
     *         changes: array{
     *             total_ranked: float|null,
     *             pending_approval: float|null,
     *             needs_manual_review: float|null
     *         }
     *     },
     *     charts: array{
     *         average_score_trend: list<array{date: string, label: string, average_score: int|null, ranked_count: int}>,
     *         score_distribution: list<array{bucket: string, label: string, count: int}>
     *     }
     * }
     */
    public function build(): array
    {
        $assessments = Assessment::query()
            ->whereNotNull('ranking_score')
            ->get([
                'id',
                'ranking_score',
                'status',
                'needs_manual_review',
                'evaluated_at',
                'created_at',
            ]);

        $cutoff = now()->subDays(7);
        $previousAssessments = $assessments
            ->filter(fn (Assessment $assessment): bool => $this->rankedBefore($assessment, $cutoff))
            ->values();

        $currentSummary = $this->summaryFor($assessments);
        $previousSummary = $this->summaryFor($previousAssessments);

        return [
            'summary' => [
                ...$currentSummary,
                'period_label' => 'Last 7 days',
                'changes' => [
                    'total_ranked' => $this->changePercent(
                        $currentSummary['total_ranked'],
                        $previousSummary['total_ranked'],
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
                'average_score_trend' => $this->averageScoreTrend($assessments),
                'score_distribution' => $this->scoreDistribution($assessments),
            ],
        ];
    }

    /**
     * @param  Collection<int, Assessment>  $assessments
     * @return array{
     *     total_ranked: int,
     *     pending_approval: int,
     *     needs_manual_review: int,
     *     average_ranking_score: int|null
     * }
     */
    private function summaryFor(Collection $assessments): array
    {
        return [
            'total_ranked' => $assessments->count(),
            'pending_approval' => $assessments->where('status', AssessmentStatus::PendingApproval)->count(),
            'needs_manual_review' => $assessments->where('needs_manual_review', true)->count(),
            'average_ranking_score' => $assessments->isEmpty()
                ? null
                : (int) round((float) $assessments->avg('ranking_score')),
        ];
    }

    /**
     * @param  Collection<int, Assessment>  $assessments
     * @return list<array{date: string, label: string, average_score: int|null, ranked_count: int}>
     */
    private function averageScoreTrend(Collection $assessments): array
    {
        $days = collect(range(6, 0))->map(fn (int $daysAgo): array => [
            'start' => now()->subDays($daysAgo)->startOfDay(),
            'end' => now()->subDays($daysAgo)->endOfDay(),
        ]);

        return $days
            ->map(function (array $day) use ($assessments): array {
                $dayAssessments = $assessments->filter(function (Assessment $assessment) use ($day): bool {
                    $rankedAt = $assessment->evaluated_at ?? $assessment->created_at;

                    return $rankedAt !== null && $rankedAt->between($day['start'], $day['end']);
                });

                return [
                    'date' => $day['start']->toDateString(),
                    'label' => $day['start']->format('D'),
                    'average_score' => $dayAssessments->isEmpty()
                        ? null
                        : (int) round((float) $dayAssessments->avg('ranking_score')),
                    'ranked_count' => $dayAssessments->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Assessment>  $assessments
     * @return list<array{bucket: string, label: string, count: int}>
     */
    private function scoreDistribution(Collection $assessments): array
    {
        $buckets = [
            ['bucket' => '0-49', 'label' => '0–49', 'min' => 0, 'max' => 49],
            ['bucket' => '50-69', 'label' => '50–69', 'min' => 50, 'max' => 69],
            ['bucket' => '70-84', 'label' => '70–84', 'min' => 70, 'max' => 84],
            ['bucket' => '85-100', 'label' => '85–100', 'min' => 85, 'max' => 100],
        ];

        return collect($buckets)
            ->map(fn (array $bucket): array => [
                'bucket' => $bucket['bucket'],
                'label' => $bucket['label'],
                'count' => $assessments
                    ->filter(function (Assessment $assessment) use ($bucket): bool {
                        $score = (int) $assessment->ranking_score;

                        return $score >= $bucket['min'] && $score <= $bucket['max'];
                    })
                    ->count(),
            ])
            ->values()
            ->all();
    }

    private function rankedBefore(Assessment $assessment, CarbonInterface $cutoff): bool
    {
        $rankedAt = $assessment->evaluated_at ?? $assessment->created_at;

        return $rankedAt !== null && $rankedAt->lt($cutoff);
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
