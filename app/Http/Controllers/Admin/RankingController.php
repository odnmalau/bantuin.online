<?php

namespace App\Http\Controllers\Admin;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\CandidateRankingCalculator;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    /**
     * Show the transparent candidate ranking dashboard.
     */
    public function index(CandidateRankingCalculator $rankingCalculator): Response
    {
        $assessments = Assessment::query()
            ->with([
                'campaign:id,title,role_title',
                'user:id,name,email',
            ])
            ->whereNotNull('ranking_score')
            ->orderByDesc('ranking_score')
            ->latest()
            ->get();

        return Inertia::render('admin/rankings/index', [
            'formula' => $rankingCalculator->configuredFormula(),
            'weights' => $rankingCalculator->configuredWeights(),
            'summary' => [
                'total_ranked' => $assessments->count(),
                'pending_approval' => $assessments->where('status', AssessmentStatus::PendingApproval)->count(),
                'needs_manual_review' => $assessments->where('needs_manual_review', true)->count(),
                'average_ranking_score' => $assessments->isEmpty()
                    ? null
                    : (int) round((float) $assessments->avg('ranking_score')),
            ],
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
                    'matched_skills' => data_get($assessment->resume_payload, 'matched_skills', []),
                    'missing_skills' => data_get($assessment->resume_payload, 'missing_skills', []),
                    'interview_probes' => data_get($assessment->resume_payload, 'interview_probes', []),
                    'section_scores' => data_get($assessment->ranking_payload, 'section_scores', []),
                    'evaluated_at' => $assessment->evaluated_at,
                ]),
        ]);
    }
}
