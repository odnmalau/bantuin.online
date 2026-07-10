<?php

namespace App\Http\Controllers\Admin;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
     * Show the candidate ranking leaderboard.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['all', ...array_column(AssessmentStatus::selectOptions(), 'value')])],
            'date_range' => ['nullable', 'string', Rule::in(array_column(self::dateRangeOptions(), 'value'))],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $dateRange = (string) ($filters['date_range'] ?? 'all');

        $assessments = Assessment::query()
            ->with([
                'campaign:id,title,role_title',
                'user:id,name,email',
            ])
            ->whereNotNull('ranking_score')
            ->when($search !== '', fn (Builder $query) => $this->applyRankingSearch($query, $search))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($dateRange !== 'all', fn (Builder $query) => $this->applyDateRange($query, $dateRange))
            ->orderByDesc('ranking_score')
            ->latest()
            ->get();

        return Inertia::render('admin/rankings/index', [
            'rankings' => $assessments
                ->values()
                ->map(fn (Assessment $assessment, int $index): array => [
                    'rank' => $index + 1,
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
                ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_range' => $dateRange,
            ],
            'statusOptions' => AssessmentStatus::selectOptions(),
            'dateRangeOptions' => self::dateRangeOptions(),
        ]);
    }

    private function applyRankingSearch(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query
                ->whereHas('user', function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                })
                ->orWhereHas('campaign', function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(role_title) LIKE ?', [$term]);
                });
        });
    }

    private function applyDateRange(Builder $query, string $dateRange): void
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

        $query->whereBetween('evaluated_at', [$from, $to]);
    }
}
