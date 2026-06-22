<?php

namespace App\Services;

use App\Models\Assessment;
use App\QuestionGradingMode;
use App\QuestionType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeterministicAssessmentGrader
{
    /**
     * Grade deterministic question snapshots and return a normalized 0-100 score.
     */
    public function grade(Assessment $assessment): ?int
    {
        return $this->breakdown($assessment)['score'];
    }

    /**
     * @return array{score: int|null, section_scores: array<int, array<string, mixed>>}
     */
    public function breakdown(Assessment $assessment): array
    {
        $earnedPoints = 0.0;
        $totalPoints = 0.0;
        $questionScores = [];

        foreach ($assessment->answers_payload ?? [] as $answerSnapshot) {
            $questionScore = $this->gradeDeterministicSnapshot($answerSnapshot);

            if ($questionScore === null) {
                continue;
            }

            $totalPoints += $questionScore['total_points'];
            $earnedPoints += $questionScore['earned_points'];
            $questionScores[] = $questionScore;
        }

        if ($totalPoints <= 0.0) {
            return [
                'score' => null,
                'section_scores' => [],
            ];
        }

        $sectionScores = $this->sectionScores($questionScores);

        return [
            'score' => $this->score($earnedPoints, $totalPoints, $sectionScores),
            'section_scores' => $sectionScores,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gradeDeterministicSnapshot(mixed $answerSnapshot): ?array
    {
        if (! is_array($answerSnapshot)) {
            return null;
        }

        $questionType = QuestionType::tryFrom((string) data_get($answerSnapshot, 'type'));

        if (! $questionType?->usesDeterministicGrading()) {
            return null;
        }

        $gradingMode = QuestionGradingMode::tryFrom((string) data_get($answerSnapshot, 'grading_mode'))
            ?? QuestionGradingMode::Deterministic;

        if ($gradingMode !== QuestionGradingMode::Deterministic) {
            return null;
        }

        $correctAnswer = data_get($answerSnapshot, 'correct_answer');

        if ($correctAnswer === null) {
            return null;
        }

        $points = $this->points($answerSnapshot);
        $isCorrect = $this->isCorrect($questionType, data_get($answerSnapshot, 'answer'), $correctAnswer);
        $earnedPoints = $isCorrect ? $points : 0.0;

        return [
            'section_key' => $this->sectionKey($answerSnapshot),
            'section_id' => $this->sectionId($answerSnapshot),
            'section_title' => data_get($answerSnapshot, 'section_title', 'Unsectioned'),
            'section_weight' => $this->sectionWeight($answerSnapshot),
            'earned_points' => $earnedPoints,
            'total_points' => $points,
        ];
    }

    /**
     * @param  array<string, mixed>  $answerSnapshot
     */
    private function points(array $answerSnapshot): float
    {
        $points = data_get($answerSnapshot, 'points', 1);

        if (! is_numeric($points)) {
            return 1.0;
        }

        return max((float) $points, 0.0);
    }

    /**
     * @param  array<string, mixed>  $answerSnapshot
     */
    private function sectionId(array $answerSnapshot): mixed
    {
        return data_get($answerSnapshot, 'section_id', data_get($answerSnapshot, 'campaign_section_id'));
    }

    /**
     * @param  array<string, mixed>  $answerSnapshot
     */
    private function sectionKey(array $answerSnapshot): string
    {
        $sectionId = $this->sectionId($answerSnapshot);

        if ($sectionId !== null) {
            return 'section:'.$sectionId;
        }

        return 'section:'.Str::slug((string) data_get($answerSnapshot, 'section_title', 'unsectioned'));
    }

    /**
     * @param  array<string, mixed>  $answerSnapshot
     */
    private function sectionWeight(array $answerSnapshot): int
    {
        $weight = data_get($answerSnapshot, 'section_weight');

        if (! is_numeric($weight)) {
            return 100;
        }

        return max(0, (int) $weight);
    }

    /**
     * @param  array<int, array<string, mixed>>  $questionScores
     * @return array<int, array<string, mixed>>
     */
    private function sectionScores(array $questionScores): array
    {
        return collect($questionScores)
            ->groupBy('section_key')
            ->map(function ($scores): array {
                $firstScore = $scores->first();
                $earnedPoints = (float) $scores->sum('earned_points');
                $totalPoints = (float) $scores->sum('total_points');

                return [
                    'section_id' => $firstScore['section_id'],
                    'title' => $firstScore['section_title'],
                    'weight' => $firstScore['section_weight'],
                    'earned_points' => $earnedPoints,
                    'total_points' => $totalPoints,
                    'score' => $totalPoints > 0.0
                        ? $this->clampScore((int) round(($earnedPoints / $totalPoints) * 100))
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectionScores
     */
    private function score(float $earnedPoints, float $totalPoints, array $sectionScores): int
    {
        if (count($sectionScores) <= 1) {
            return $this->percentageScore($earnedPoints, $totalPoints);
        }

        $sectionScoreCollection = collect($sectionScores);
        $totalWeight = $sectionScoreCollection->sum('weight');

        if ($totalWeight <= 0) {
            return $this->percentageScore($earnedPoints, $totalPoints);
        }

        $weightedScore = $sectionScoreCollection
            ->filter(fn (array $section): bool => $section['score'] !== null)
            ->sum(fn (array $section): float => $section['score'] * ($section['weight'] / $totalWeight));

        return $this->clampScore((int) round($weightedScore));
    }

    private function isCorrect(QuestionType $questionType, mixed $candidateAnswer, mixed $correctAnswer): bool
    {
        return match ($questionType) {
            QuestionType::MultipleChoice => $this->normalizeSet($candidateAnswer) === $this->normalizeSet($correctAnswer),
            QuestionType::YesNo => $this->normalizeText($candidateAnswer) === $this->normalizeText(Arr::first(Arr::wrap($correctAnswer))),
            QuestionType::FillBlank => in_array($this->normalizeText($candidateAnswer), $this->normalizeSet($correctAnswer), true),
            QuestionType::MatchingPairs => $this->normalizePairs($candidateAnswer) === $this->normalizePairs($correctAnswer),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSet(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->flatten()
            ->map(fn (mixed $item): string => $this->normalizeText($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normalizePairs(mixed $value): array
    {
        if (! is_array($value)) {
            return $this->pairEntriesFromString($value) ?: $this->normalizeSet($value);
        }

        if (! array_is_list($value)) {
            return $this->finalizePairEntries(collect($value)
                ->map(fn (mixed $right, string|int $left): string => $this->pairEntryFromValues($left, $right))
            );
        }

        return $this->finalizePairEntries(collect($value)
            ->map(function (mixed $pair): string {
                if (is_array($pair)) {
                    $left = data_get($pair, 'left', data_get($pair, 'key', Arr::first($pair)));
                    $right = data_get($pair, 'right', data_get($pair, 'value', Arr::last($pair)));

                    return $this->pairEntryFromValues($left, $right);
                }

                return $this->pairEntryFromString($pair) ?? $this->normalizeText($pair);
            })
        );
    }

    private function pairEntryFromValues(mixed $left, mixed $right): string
    {
        $normalizedLeft = $this->normalizeText($left);
        $normalizedRight = $this->normalizeText($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return '';
        }

        return $normalizedLeft.'='.$normalizedRight;
    }

    private function pairEntryFromString(mixed $value): ?string
    {
        $normalizedValue = $this->normalizeText($value);

        if ($normalizedValue === '') {
            return null;
        }

        preg_match('/^(.+?)\s*(?:=>|=|:|->|→|\t)\s*(.+)$/u', (string) $value, $matches);

        if ($matches === []) {
            return null;
        }

        return $this->pairEntryFromValues($matches[1], $matches[2]);
    }

    /**
     * @return array<int, string>
     */
    private function pairEntriesFromString(mixed $value): array
    {
        return $this->finalizePairEntries(
            collect(preg_split('/[\r\n]+/', (string) $value) ?: [])
                ->map(fn (string $line): ?string => $this->pairEntryFromString($line)),
        );
    }

    /**
     * @param  Collection<int, string|null>|array<int, string|null>  $entries
     * @return array<int, string>
     */
    private function finalizePairEntries(Collection|array $entries): array
    {
        return collect($entries)
            ->filter(fn (?string $pair): bool => $pair !== null && $pair !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeText(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->squish()
            ->toString();
    }

    private function percentageScore(float $earnedPoints, float $totalPoints): int
    {
        return $this->clampScore((int) round(($earnedPoints / $totalPoints) * 100));
    }

    private function clampScore(int $score): int
    {
        return min(100, max(0, $score));
    }
}
