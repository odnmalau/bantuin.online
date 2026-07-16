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
    public function calculate(Assessment $assessment, ?int $assessmentScore): array
    {
        $weightConfiguration = $this->resolveWeightConfiguration();
        $components = [
            'resume_score' => $this->normalizeNullableScore($assessment->resume_score),
            'assessment_score' => $this->normalizeNullableScore($assessmentScore),
        ];
        $weights = $weightConfiguration['weights'];
        $availableComponents = $this->availableComponents($components, $weights);
        $missingComponents = $this->missingComponents($components, $availableComponents, $weights);

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

        return [
            'score' => $rankingScore,
            'payload' => [
                'components' => $components,
                'configured_weights' => $weights,
                'normalized_weights' => $normalizedWeights,
                'missing_components' => $missingComponents,
                'weighting_mode' => 'assessment_only',
                'weight_source' => $weightConfiguration['source'],
                'formula' => $this->formula($weights),
            ],
        ];
    }

    /**
     * @return array{resume_score: int, assessment_score: int}
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
     *     weights: array{resume_score: int, assessment_score: int},
     *     source: string
     * }
     */
    private function resolveWeightConfiguration(): array
    {
        return [
            'weights' => Campaign::defaultRankingWeights(),
            'source' => 'config_default',
        ];
    }

    /**
     * @param  array<string, int|null>  $components
     * @param  array<string, int>  $weights
     * @return array<string, int|null>
     */
    private function availableComponents(array $components, array $weights): array
    {
        return collect($components)
            ->filter(fn (?int $score, string $component): bool => $score !== null && ($weights[$component] ?? 0) > 0)
            ->all();
    }

    /**
     * @param  array<string, int|null>  $components
     * @param  array<string, int|null>  $availableComponents
     * @param  array<string, int>  $weights
     * @return array<int, string>
     */
    private function missingComponents(array $components, array $availableComponents, array $weights): array
    {
        return collect($components)
            ->filter(fn (?int $score, string $component): bool => $score === null && ($weights[$component] ?? 0) > 0)
            ->keys()
            ->values()
            ->all();
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
            ->filter(fn (int $weight): bool => $weight > 0)
            ->map(fn (int $weight, string $component): string => $component.' * '.number_format($weight / 100, 2))
            ->implode(' + ');
    }
}
