<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Campaign;

class CandidateRankingCalculator
{
    /**
     * Calculate the auditable final ranking score from available score components.
     *
     * @return array{score: int|null, payload: array<string, mixed>}
     */
    public function calculate(Assessment $assessment, ?int $mcqScore, ?int $essayScore, array $sectionScores = []): array
    {
        $assessment->loadMissing('campaign');

        $weightConfiguration = $this->resolveWeightConfiguration($assessment);
        $components = [
            'resume_score' => $this->normalizeNullableScore($assessment->resume_score),
            'essay_score' => $this->normalizeNullableScore($essayScore),
            'mcq_score' => $this->normalizeNullableScore($mcqScore),
        ];
        $weights = $weightConfiguration['weights'];
        $availableComponents = $this->availableComponents($components);
        $missingComponents = $this->missingComponents($components, $availableComponents);

        if ($availableComponents === []) {
            return [
                'score' => null,
                'payload' => [
                    'components' => $components,
                    'configured_weights' => $weights,
                    'normalized_weights' => [],
                    'missing_components' => $missingComponents,
                    'weighting_mode' => 'unavailable',
                    'weight_source' => $weightConfiguration['source'],
                ],
            ];
        }

        $normalizedWeights = $this->normalizedWeights($availableComponents, $weights);
        $score = 0.0;

        foreach ($availableComponents as $key => $componentScore) {
            $score += $componentScore * ($normalizedWeights[$key] / 100);
        }

        $rankingScore = $this->normalizeScore((int) round($score));
        $weightingMode = $missingComponents === []
            ? 'configured_weights'
            : 'normalized_available_components';

        return [
            'score' => $rankingScore,
            'payload' => [
                'components' => $components,
                'configured_weights' => $weights,
                'normalized_weights' => $normalizedWeights,
                'missing_components' => $missingComponents,
                'weighting_mode' => $weightingMode,
                'weight_source' => $weightConfiguration['source'],
                'formula' => $this->formula($weights),
                'section_scores' => $sectionScores,
            ],
        ];
    }

    /**
     * @return array{resume_score: int, essay_score: int, mcq_score: int}
     */
    public function configuredWeights(): array
    {
        return Campaign::defaultRankingWeights();
    }

    public function configuredFormula(): string
    {
        return $this->formula($this->configuredWeights());
    }

    /**
     * @return array{
     *     weights: array{resume_score: int, essay_score: int, mcq_score: int},
     *     source: string
     * }
     */
    private function resolveWeightConfiguration(Assessment $assessment): array
    {
        if ($assessment->campaign === null) {
            return [
                'weights' => Campaign::defaultRankingWeights(),
                'source' => 'config_default',
            ];
        }

        return [
            'weights' => $assessment->campaign->resolvedRankingWeights(),
            'source' => $assessment->campaign->hasConfiguredRankingWeights() ? 'campaign' : 'config_default',
        ];
    }

    /**
     * @param  array<string, int|null>  $components
     * @return array<string, int|null>
     */
    private function availableComponents(array $components): array
    {
        return array_filter($components, fn (?int $score): bool => $score !== null);
    }

    /**
     * @param  array<string, int|null>  $components
     * @param  array<string, int|null>  $availableComponents
     * @return array<int, string>
     */
    private function missingComponents(array $components, array $availableComponents): array
    {
        return array_values(array_diff(array_keys($components), array_keys($availableComponents)));
    }

    /**
     * @param  array<string, int>  $availableComponents
     * @param  array<string, int>  $weights
     * @return array<string, float>
     */
    private function normalizedWeights(array $availableComponents, array $weights): array
    {
        $availableWeights = collect(array_keys($availableComponents))
            ->mapWithKeys(fn (string $key): array => [$key => $weights[$key] ?? 0])
            ->all();
        $totalWeight = array_sum($availableWeights);

        if ($totalWeight <= 0) {
            $equalWeight = 100 / count($availableComponents);

            return collect(array_keys($availableComponents))
                ->mapWithKeys(fn (string $key): array => [$key => round($equalWeight, 4)])
                ->all();
        }

        return collect($availableWeights)
            ->map(fn (int $weight): float => round(($weight / $totalWeight) * 100, 4))
            ->all();
    }

    private function normalizeNullableScore(mixed $score): ?int
    {
        if ($score === null || ! is_numeric($score)) {
            return null;
        }

        return $this->normalizeScore((int) $score);
    }

    private function normalizeScore(int $score): int
    {
        return min(100, max(0, $score));
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function formula(array $weights): string
    {
        return collect($weights)
            ->map(fn (int $weight, string $component): string => $component.' * '.number_format($weight / 100, 2))
            ->implode(' + ');
    }
}
