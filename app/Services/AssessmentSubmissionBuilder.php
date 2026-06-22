<?php

namespace App\Services;

use App\Models\CampaignQuestion;
use App\QuestionType;
use Illuminate\Support\Collection;

class AssessmentSubmissionBuilder
{
    /**
     * @param  Collection<int, CampaignQuestion>  $questions
     * @param  array<int|string, string>  $answersByQuestionId
     * @return array<int, array<string, mixed>>
     */
    public function buildAnswersPayload(Collection $questions, array $answersByQuestionId): array
    {
        $answers = collect($answersByQuestionId)
            ->mapWithKeys(fn (string $answer, string|int $questionId): array => [(string) $questionId => $answer]);

        return $questions
            ->map(fn (CampaignQuestion $question) => $this->answerSnapshotEntry(
                $question,
                $answers->get((string) $question->id),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function answerSnapshotEntry(CampaignQuestion $question, ?string $answer): array
    {
        return [
            'question_id' => $question->id,
            'campaign_question_id' => $question->id,
            'campaign_section_id' => $question->campaign_section_id,
            'section_id' => $question->campaign_section_id,
            'section_title' => $question->section?->title,
            'section_weight' => $question->section?->weight,
            'question' => $question->prompt,
            'rubric' => $question->expected_rubric,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'grading_mode' => $question->grading_mode->value,
            'grading_mode_label' => $question->grading_mode->label(),
            'options' => $question->options ?? [],
            'correct_answer' => $question->correct_answer,
            'points' => $question->points,
            'difficulty' => $question->difficulty,
            'skill_tags' => $question->skill_tags ?? [],
            'answer' => $answer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionForCandidate(CampaignQuestion $question): array
    {
        $matchingPairs = $question->type === QuestionType::MatchingPairs
            ? $this->matchingPairsForCandidate($question->correct_answer)
            : null;

        return [
            'id' => $question->id,
            'section_id' => $question->campaign_section_id,
            'content' => $question->prompt,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'options' => $question->options ?? [],
            'matching_pairs' => $matchingPairs,
            'points' => $question->points,
            'section_title' => $question->section?->title,
            'sort_order' => $question->sort_order,
        ];
    }

    /**
     * @return array{prompts: array<int, string>, choices: array<int, string>}|null
     */
    private function matchingPairsForCandidate(mixed $correctAnswer): ?array
    {
        $pairs = $this->parseMatchingPairs($correctAnswer);

        if ($pairs === []) {
            return null;
        }

        $prompts = [];
        $choices = [];

        foreach ($pairs as $pair) {
            $prompts[] = $pair['left'];
            $choices[] = $pair['right'];
        }

        shuffle($choices);

        return [
            'prompts' => $prompts,
            'choices' => $choices,
        ];
    }

    /**
     * @return array<int, array{left: string, right: string}>
     */
    private function parseMatchingPairs(mixed $value): array
    {
        if (! is_array($value)) {
            return $this->parseMatchingPairsFromString($value);
        }

        if (! array_is_list($value)) {
            return collect($value)
                ->map(fn (mixed $right, string|int $left): array => [
                    'left' => trim((string) $left),
                    'right' => trim((string) $right),
                ])
                ->filter(fn (array $pair): bool => $pair['left'] !== '' && $pair['right'] !== '')
                ->values()
                ->all();
        }

        return collect($value)
            ->map(function (mixed $pair): ?array {
                if (is_array($pair)) {
                    $left = trim((string) data_get($pair, 'left', data_get($pair, 'key', '')));
                    $right = trim((string) data_get($pair, 'right', data_get($pair, 'value', '')));

                    if ($left === '' || $right === '') {
                        return null;
                    }

                    return ['left' => $left, 'right' => $right];
                }

                return $this->parseMatchingPairLine($pair);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{left: string, right: string}>
     */
    private function parseMatchingPairsFromString(mixed $value): array
    {
        return collect(preg_split('/[\r\n]+/', (string) $value) ?: [])
            ->map(fn (string $line): ?array => $this->parseMatchingPairLine($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{left: string, right: string}|null
     */
    private function parseMatchingPairLine(mixed $value): ?array
    {
        preg_match('/^(.+?)\s*(?:=>|=|:|->|→|\t)\s*(.+)$/u', (string) $value, $matches);

        if ($matches === []) {
            return null;
        }

        $left = trim($matches[1]);
        $right = trim($matches[2]);

        if ($left === '' || $right === '') {
            return null;
        }

        return [
            'left' => $left,
            'right' => $right,
        ];
    }
}
