<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignSection;
use Illuminate\Support\Collection;

class CampaignSectionDistribution
{
    /** @param array<string, mixed> $attributes */
    public function create(Campaign $campaign, array $attributes): CampaignSection
    {
        $sections = $this->lockedSections($campaign);
        $contribution = $sections->isEmpty() ? 100 : (int) $attributes['weight'];

        $this->distribute($sections, 100 - $contribution);

        return $campaign->sections()->create([
            ...$attributes,
            'weight' => $contribution,
            'sort_order' => ((int) $sections->max('sort_order')) + 10,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Campaign $campaign, CampaignSection $section, array $attributes): void
    {
        $sections = $this->lockedSections($campaign);
        $otherSections = $sections->where('id', '!=', $section->id)->values();
        $contribution = $otherSections->isEmpty() ? 100 : (int) $attributes['weight'];

        $this->distribute($otherSections, 100 - $contribution);
        $section->update([
            ...$attributes,
            'weight' => $contribution,
        ]);
    }

    public function normalize(Campaign $campaign): void
    {
        $this->distribute($this->lockedSections($campaign), 100);
    }

    /**
     * @param  Collection<int, CampaignSection>  $sections
     */
    private function distribute(Collection $sections, int $target): void
    {
        if ($sections->isEmpty()) {
            return;
        }

        $minimumTotal = $sections->count();
        $remaining = max(0, $target - $minimumTotal);
        $weightTotal = max(1, (int) $sections->sum(fn (CampaignSection $section): int => max(1, $section->weight)));
        $allocations = $sections->map(function (CampaignSection $section) use ($remaining, $weightTotal): array {
            $exactShare = $remaining * max(1, $section->weight) / $weightTotal;

            return [
                'section' => $section,
                'weight' => 1 + (int) floor($exactShare),
                'remainder' => $exactShare - floor($exactShare),
            ];
        });

        $unallocated = $target - (int) $allocations->sum('weight');
        $allocations = $allocations
            ->sortByDesc('remainder')
            ->values()
            ->map(function (array $allocation, int $index) use ($unallocated): array {
                if ($index < $unallocated) {
                    $allocation['weight']++;
                }

                return $allocation;
            });

        foreach ($allocations as $allocation) {
            $allocation['section']->update(['weight' => $allocation['weight']]);
        }
    }

    /** @return Collection<int, CampaignSection> */
    private function lockedSections(Campaign $campaign): Collection
    {
        return $campaign->sections()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
