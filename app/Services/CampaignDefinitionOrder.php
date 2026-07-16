<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignSection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CampaignDefinitionOrder
{
    /** @param list<int> $sectionIds */
    public function sections(Campaign $campaign, array $sectionIds): void
    {
        $sections = $campaign->sections()->lockForUpdate()->get()->keyBy('id');
        $this->ensureCompleteOrder($sections, $sectionIds, 'sections');
        $this->applyOrder($sections, $sectionIds);
    }

    /** @param list<int> $questionIds */
    public function questions(Campaign $campaign, CampaignSection $section, array $questionIds): void
    {
        if ($section->campaign_id !== $campaign->id) {
            throw ValidationException::withMessages([
                'section' => __('The selected section does not belong to this campaign.'),
            ]);
        }

        $questions = $section->questions()->lockForUpdate()->get()->keyBy('id');
        $this->ensureCompleteOrder($questions, $questionIds, 'questions');
        $this->applyOrder($questions, $questionIds);
    }

    public function nextQuestionSortOrder(Campaign $campaign, int $sectionId): int
    {
        $section = $campaign->sections()->whereKey($sectionId)->lockForUpdate()->first();

        if ($section === null) {
            throw ValidationException::withMessages([
                'campaign_section_id' => __('The selected section does not belong to this campaign.'),
            ]);
        }

        return ((int) $section->questions()->lockForUpdate()->max('sort_order')) + 10;
    }

    public function normalizeQuestions(CampaignSection $section): void
    {
        $questions = $section->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $this->applyOrder($questions, $questions->keys()->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * @param  Collection<int, mixed>  $models
     * @param  list<int>  $ids
     */
    private function ensureCompleteOrder(Collection $models, array $ids, string $field): void
    {
        $currentIds = $models->keys()->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $submittedIds = collect($ids)->sort()->values()->all();

        if ($currentIds !== $submittedIds) {
            throw ValidationException::withMessages([
                $field => __('The submitted order must include every item exactly once.'),
            ]);
        }
    }

    /**
     * @param  Collection<int, mixed>  $models
     * @param  list<int>  $ids
     */
    private function applyOrder(Collection $models, array $ids): void
    {
        foreach ($ids as $index => $id) {
            $models->get($id)->update(['sort_order' => ($index + 1) * 10]);
        }
    }
}
