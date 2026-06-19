<?php

namespace App\Services\Ai;

use App\Ai\Agents\QuestionBankGeneratorAgent;
use App\Models\BankQuestion;
use App\Models\QuestionBank;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;
use App\Services\Ai\Concerns\LimitsGeneratedQuestionCount;
use App\Services\Ai\Concerns\NormalizesGeneratedQuestions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QwenQuestionBankGenerator
{
    use ConfiguresQwenAssessmentAgent;
    use LimitsGeneratedQuestionCount;
    use NormalizesGeneratedQuestions;

    /**
     * Generate additional draft questions for a reusable question library.
     *
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     */
    public function generate(QuestionBank $questionBank, array $options): int
    {
        $questionBank->loadMissing([
            'questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $this->assertQwenApiKeyConfigured();

        $response = $this->promptStructuredAgent(
            new QuestionBankGeneratorAgent,
            $this->prompt($questionBank, $options),
            AssessmentGenerationException::class,
        );

        $questions = $this->normalizeQuestions($response->toArray());
        $questions = $this->limitQuestionsToCount(
            $questions,
            max(1, (int) ($options['question_count'] ?? 6)),
        );

        return DB::transaction(fn (): int => $this->persistDrafts($questionBank, $questions, $options));
    }

    /**
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     * @return array<string, mixed>
     */
    public function promptPayload(QuestionBank $questionBank, array $options): array
    {
        return [
            'instruction' => 'Generate reusable library questions. Output JSON matching the structured schema.',
            'question_bank' => [
                'title' => $questionBank->title,
                'description' => $questionBank->description,
                'skill_area' => $questionBank->skill_area,
                'difficulty' => $questionBank->difficulty,
            ],
            'generation_options' => [
                'question_count' => (int) ($options['question_count'] ?? 6),
                'language' => (string) ($options['language'] ?? 'English'),
                'difficulty' => (string) ($options['difficulty'] ?? 'mixed'),
                'question_mix' => $options['question_mix'] ?? null,
            ],
            'allowed_question_types' => QuestionType::selectOptions(),
            'existing_questions' => $questionBank->questions
                ->map(fn (BankQuestion $question): array => [
                    'type' => $question->type->value,
                    'prompt' => $question->prompt,
                    'status' => $question->status->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestions(array $output): array
    {
        $questions = Arr::get($output, 'questions');

        if (! is_array($questions) || $questions === []) {
            throw AssessmentGenerationException::invalidOutput('questions must be a non-empty array.');
        }

        return collect($questions)
            ->values()
            ->map(fn (mixed $question, int $index): array => $this->normalizeGeneratedQuestion($question, $index))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     */
    private function persistDrafts(QuestionBank $questionBank, array $questions, array $options): int
    {
        $createdQuestions = 0;
        $baseSortOrder = (int) $questionBank->questions()->max('sort_order');

        foreach ($questions as $index => $question) {
            $questionBank->questions()->create([
                ...Arr::except($question, ['sort_order']),
                'sort_order' => $baseSortOrder + (($index + 1) * 10),
                'ai_generated' => true,
                'status' => QuestionStatus::Draft,
            ]);

            $createdQuestions++;
        }

        $audit = $questionBank->ai_generation_audit ?? [];
        $audit[] = $this->generationAuditEntry($options, $createdQuestions);

        $questionBank->update([
            'ai_generation_audit' => $audit,
        ]);

        return $createdQuestions;
    }

    /**
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     * @return array{
     *     generated_at: string,
     *     provider: string,
     *     model: string,
     *     prompt_version: string,
     *     agent: string,
     *     generation_options: array<string, mixed>,
     *     questions_created: int,
     * }
     */
    private function generationAuditEntry(array $options, int $questionsCreated): array
    {
        return [
            ...$this->generationAuditBase(QuestionBankGeneratorAgent::class, $options),
            'questions_created' => $questionsCreated,
        ];
    }

    private function prompt(QuestionBank $questionBank, array $options): string
    {
        return $this->encodePrompt($this->promptPayload($questionBank, $options));
    }
}
