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
            ->orderByRaw($this->attentionOrderSql())
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
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

    private function attentionOrderSql(): string
    {
        $active = CampaignStatus::Active->value;
        $questionReview = CampaignStatus::QuestionReview->value;
        $draft = CampaignStatus::Draft->value;

        return <<<SQL
            case
                when status = '{$active}'
                    and (pending_approval_count > 0 or needs_manual_review_count > 0)
                    then 0
                when status = '{$questionReview}' then 1
                when status = '{$draft}' then 2
                when status = '{$active}' then 3
                else 4
            end
            SQL;
    }
}
