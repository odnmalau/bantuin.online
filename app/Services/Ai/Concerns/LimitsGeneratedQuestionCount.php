<?php

namespace App\Services\Ai\Concerns;

use App\Services\Ai\AssessmentGenerationException;

trait LimitsGeneratedQuestionCount
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function limitSectionsToQuestionCount(array $sections, int $questionCount): array
    {
        $this->assertQuestionCountAtLeastOne($questionCount);

        $remaining = $questionCount;
        $limitedSections = [];

        foreach ($sections as $section) {
            if ($remaining <= 0) {
                break;
            }

            $questions = array_slice($section['questions'], 0, $remaining);

            if ($questions === []) {
                continue;
            }

            $remaining -= count($questions);
            $limitedSections[] = [
                ...$section,
                'questions' => $questions,
            ];
        }

        if ($limitedSections === []) {
            throw AssessmentGenerationException::invalidOutput('no questions remained after applying question_count limit.');
        }

        return $limitedSections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    protected function limitQuestionsToCount(array $questions, int $questionCount): array
    {
        $this->assertQuestionCountAtLeastOne($questionCount);

        $limitedQuestions = array_slice($questions, 0, $questionCount);

        if ($limitedQuestions === []) {
            throw AssessmentGenerationException::invalidOutput('no questions remained after applying question_count limit.');
        }

        return $limitedQuestions;
    }

    private function assertQuestionCountAtLeastOne(int $questionCount): void
    {
        if ($questionCount < 1) {
            throw AssessmentGenerationException::invalidOutput('question_count must be at least 1.');
        }
    }
}
