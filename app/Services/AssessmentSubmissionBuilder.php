<?php

namespace App\Services;

use App\Models\CampaignQuestion;
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
            'max_characters' => $question->type->maxCharacters(),
            'points' => $question->points,
            'difficulty' => $question->difficulty,
            'answer' => $answer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionForCandidate(CampaignQuestion $question): array
    {
        return [
            'id' => $question->id,
            'section_id' => $question->campaign_section_id,
            'content' => $question->prompt,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'max_characters' => $question->type->maxCharacters(),
            'points' => $question->points,
            'section_title' => $question->section?->title,
            'sort_order' => $question->sort_order,
        ];
    }
}
