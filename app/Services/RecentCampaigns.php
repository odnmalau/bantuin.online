<?php

namespace App\Services;

use App\AssessmentStatus;
use App\CampaignStatus;
use App\Models\Campaign;

class RecentCampaigns
{
    /**
     * Build attention-first recent campaign cards for the admin dashboard.
     *
     * Priority: active campaigns needing review, question review, draft,
     * then remaining active campaigns. Archived campaigns are excluded.
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     role_title: string,
     *     seniority: string,
     *     status: string,
     *     status_label: string,
     *     pending_approval_count: int,
     *     needs_manual_review_count: int,
     *     ranked_count: int,
     *     updated_at: string|null
     * }>
     */
    public function build(int $limit = 6): array
    {
        return Campaign::query()
            ->select([
                'id',
                'title',
                'role_title',
                'seniority',
                'status',
                'updated_at',
            ])
            ->withCount([
                'assessments as ranked_count' => fn ($query) => $query->whereNotNull('ranking_score'),
                'assessments as pending_approval_count' => fn ($query) => $query
                    ->where('status', AssessmentStatus::PendingApproval),
                'assessments as needs_manual_review_count' => fn ($query) => $query
                    ->where('needs_manual_review', true),
            ])
            ->where('status', '!=', CampaignStatus::Archived)
            ->orderByDesc('updated_at')
            ->get()
            ->sort(fn (Campaign $left, Campaign $right): int => $this->compareAttention($left, $right))
            ->take($limit)
            ->values()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'role_title' => $campaign->role_title,
                'seniority' => $campaign->seniority,
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'pending_approval_count' => (int) $campaign->pending_approval_count,
                'needs_manual_review_count' => (int) $campaign->needs_manual_review_count,
                'ranked_count' => (int) $campaign->ranked_count,
                'updated_at' => $campaign->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    private function compareAttention(Campaign $left, Campaign $right): int
    {
        $priority = $this->attentionPriority($left) <=> $this->attentionPriority($right);

        if ($priority !== 0) {
            return $priority;
        }

        return ($right->updated_at?->getTimestamp() ?? 0)
            <=> ($left->updated_at?->getTimestamp() ?? 0);
    }

    private function attentionPriority(Campaign $campaign): int
    {
        $needsReview = (int) $campaign->pending_approval_count > 0
            || (int) $campaign->needs_manual_review_count > 0;

        if ($campaign->status === CampaignStatus::Active && $needsReview) {
            return 0;
        }

        return match ($campaign->status) {
            CampaignStatus::QuestionReview => 1,
            CampaignStatus::Draft => 2,
            CampaignStatus::Active => 3,
            default => 4,
        };
    }
}
