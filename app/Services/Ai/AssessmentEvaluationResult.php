<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

final readonly class AssessmentEvaluationResult
{
    /**
     * @param  list<array<string, mixed>>  $questionEvaluations
     * @param  list<array<string, mixed>>  $sectionScores
     */
    public function __construct(
        public int $score,
        public int $confidence,
        public string $justification,
        public array $questionEvaluations,
        public array $sectionScores,
        public ?string $emailSubject = null,
        public ?string $emailBody = null,
    ) {}

    /**
     * @param  array<string, mixed>  $output
     * @param  list<array<string, mixed>>  $answers
     */
    public static function fromStructuredOutput(array $output, int $passingScore, array $answers): self
    {
        $rawEvaluations = Arr::get($output, 'question_evaluations');
        $justification = Arr::get($output, 'justification');

        if (! is_array($rawEvaluations) || ! is_string($justification) || trim($justification) === '') {
            throw AssessmentEvaluationException::invalidOutput('question_evaluations and justification are required.');
        }

        $answersByQuestionId = collect($answers)->keyBy(fn (array $answer): string => (string) ($answer['question_id'] ?? ''));
        $evaluationsByQuestionId = collect($rawEvaluations)->mapWithKeys(function (mixed $evaluation): array {
            if (! is_array($evaluation)) {
                throw AssessmentEvaluationException::invalidOutput('each question evaluation must be an object.');
            }

            $questionId = $evaluation['question_id'] ?? null;

            if (! is_int($questionId) && ! (is_string($questionId) && ctype_digit($questionId))) {
                throw AssessmentEvaluationException::invalidOutput('question_id must be an integer.');
            }

            return [(string) $questionId => $evaluation];
        });

        if ($evaluationsByQuestionId->count() !== count($rawEvaluations)) {
            throw AssessmentEvaluationException::invalidOutput('question_evaluations must not contain duplicate question IDs.');
        }

        if ($answersByQuestionId->keys()->sort()->values()->all() !== $evaluationsByQuestionId->keys()->sort()->values()->all()) {
            throw AssessmentEvaluationException::invalidOutput('question_evaluations must contain exactly one result for every submitted question.');
        }

        $questionEvaluations = $answersByQuestionId
            ->map(function (array $answer, string $questionId) use ($evaluationsByQuestionId): array {
                $evaluation = $evaluationsByQuestionId->get($questionId);
                $score = $evaluation['score'] ?? null;
                $confidence = $evaluation['confidence'] ?? null;
                $questionJustification = $evaluation['justification'] ?? null;

                if (! is_int($score) || $score < 0 || $score > 100) {
                    throw AssessmentEvaluationException::invalidOutput('each question score must be an integer from 0 to 100.');
                }

                if (! is_int($confidence) || $confidence < 0 || $confidence > 100) {
                    throw AssessmentEvaluationException::invalidOutput('each question confidence must be an integer from 0 to 100.');
                }

                if (! is_string($questionJustification) || trim($questionJustification) === '') {
                    throw AssessmentEvaluationException::invalidOutput('each question justification must be a non-empty string.');
                }

                $points = max(1, (int) ($answer['points'] ?? 1));

                return [
                    'question_id' => (int) $questionId,
                    'section_id' => $answer['section_id'] ?? $answer['campaign_section_id'] ?? null,
                    'section_title' => $answer['section_title'] ?? null,
                    'section_weight' => max(1, (int) ($answer['section_weight'] ?? 100)),
                    'score' => $score,
                    'confidence' => $confidence,
                    'points' => $points,
                    'earned_points' => round($points * ($score / 100), 2),
                    'justification' => trim($questionJustification),
                ];
            })
            ->values();

        $sectionScores = $questionEvaluations
            ->groupBy(fn (array $evaluation): string => (string) ($evaluation['section_id'] ?? 'unsectioned'))
            ->map(function ($evaluations): array {
                $totalPoints = (int) $evaluations->sum('points');
                $earnedPoints = round((float) $evaluations->sum('earned_points'), 2);

                return [
                    'section_id' => $evaluations->first()['section_id'],
                    'title' => $evaluations->first()['section_title'] ?: 'Assessment',
                    'weight' => (int) $evaluations->first()['section_weight'],
                    'earned_points' => $earnedPoints,
                    'total_points' => $totalPoints,
                    'score' => $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0,
                ];
            })
            ->values();

        $totalSectionWeight = max(1, (int) $sectionScores->sum('weight'));
        $score = (int) round($sectionScores->sum(
            fn (array $section): float => $section['score'] * ($section['weight'] / $totalSectionWeight),
        ));
        $totalQuestionPoints = max(1, (int) $questionEvaluations->sum('points'));
        $confidence = (int) round($questionEvaluations->sum(
            fn (array $evaluation): float => $evaluation['confidence'] * ($evaluation['points'] / $totalQuestionPoints),
        ));
        $emailSubject = Arr::get($output, 'email.subject');
        $emailBody = Arr::get($output, 'email.body');

        return new self(
            score: min(100, max(0, $score)),
            confidence: min(100, max(0, $confidence)),
            justification: trim($justification),
            questionEvaluations: $questionEvaluations->all(),
            sectionScores: $sectionScores->all(),
            emailSubject: is_string($emailSubject) ? trim($emailSubject) : null,
            emailBody: is_string($emailBody) ? trim($emailBody) : null,
        );
    }

    public function withEmailDraft(?string $subject, ?string $body): self
    {
        return new self(
            score: $this->score,
            confidence: $this->confidence,
            justification: $this->justification,
            questionEvaluations: $this->questionEvaluations,
            sectionScores: $this->sectionScores,
            emailSubject: $subject,
            emailBody: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'score' => $this->score,
            'confidence' => $this->confidence,
            'justification' => $this->justification,
            'question_evaluations' => $this->questionEvaluations,
            'section_scores' => $this->sectionScores,
        ];
    }

    public function hasConfidenceBelow(int $threshold): bool
    {
        return $this->confidence < $threshold
            || collect($this->questionEvaluations)->contains(
                fn (array $evaluation): bool => $evaluation['confidence'] < $threshold,
            );
    }
}
