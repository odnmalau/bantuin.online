<?php

namespace App\Services\Ai;

use App\Ai\Agents\AssessmentGeneratorAgent;
use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignSection;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\Ai\Concerns\ConfiguresQwenAssessmentAgent;
use App\Services\Ai\Concerns\LimitsGeneratedQuestionCount;
use App\Services\Ai\Concerns\NormalizesGeneratedQuestions;
use App\Services\CampaignLifecycleService;
use App\TeamStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class QwenAssessmentGenerator
{
    use ConfiguresQwenAssessmentAgent;
    use LimitsGeneratedQuestionCount;
    use NormalizesGeneratedQuestions;

    public function __construct(private CampaignLifecycleService $lifecycle) {}

    /**
     * Generate draft sections and questions for a campaign.
     *
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     */
    public function generate(Campaign $campaign, array $options): int
    {
        $expectedTeamId = $campaign->team_id;
        $this->ensureCampaignTeamIsWritable($campaign, $expectedTeamId);

        $campaign->loadMissing([
            'sections.questions',
        ]);

        $this->assertQwenApiKeyConfigured();

        $prompt = $this->prompt($campaign, $options);

        $response = $this->promptStructuredAgent(
            new AssessmentGeneratorAgent,
            $prompt,
            AssessmentGenerationException::class,
        );

        $sections = $this->normalizeSections($response->toArray());
        $sections = $this->limitSectionsToQuestionCount(
            $sections,
            max(1, (int) ($options['question_count'] ?? 6)),
        );

        return DB::transaction(function () use ($campaign, $expectedTeamId, $sections, $options): int {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->lifecycle->assertDefinitionEditable($lockedCampaign);
            $this->ensureCampaignTeamIsWritable($lockedCampaign, $expectedTeamId, true);

            return $this->persistDrafts($lockedCampaign, $sections, $options);
        });
    }

    /**
     * Build the prompt payload sent to Qwen.
     *
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     * @return array<string, mixed>
     */
    public function promptPayload(Campaign $campaign, array $options): array
    {
        return [
            'instruction' => 'Generate an assessment draft. Output JSON matching the structured schema.',
            'campaign' => [
                'title' => $campaign->title,
                'role_title' => $campaign->role_title,
                'seniority' => $campaign->seniority,
                'job_description' => $campaign->job_description,
                'required_skills' => $campaign->required_skills ?? [],
                'language' => $campaign->language ?? 'English',
                'threshold_score' => $campaign->threshold_score,
                'ai_generation_notes' => $this->generationNotes(),
            ],
            'generation_options' => [
                'question_count' => (int) ($options['question_count'] ?? 6),
                'language' => (string) ($options['language'] ?? $campaign->language ?? 'English'),
                'difficulty' => (string) ($options['difficulty'] ?? 'mixed'),
                'question_mix' => $options['question_mix'] ?? null,
            ],
            'allowed_question_types' => QuestionType::selectOptions(),
            'existing_assessment_shape' => $campaign->sections
                ->map(fn (CampaignSection $section): array => [
                    'title' => $section->title,
                    'questions' => $section->questions
                        ->map(fn ($question): array => [
                            'type' => $question->type->value,
                            'prompt' => $question->prompt,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSections(array $output): array
    {
        $sections = Arr::get($output, 'sections');

        if (! is_array($sections) || $sections === []) {
            throw AssessmentGenerationException::invalidOutput('sections must be a non-empty array.');
        }

        return collect($sections)
            ->values()
            ->map(fn (mixed $section, int $index): array => $this->normalizeSection($section, $index))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSection(mixed $section, int $index): array
    {
        if (! is_array($section)) {
            throw AssessmentGenerationException::invalidOutput('each section must be an object.');
        }

        $title = $this->requiredGeneratedString($section, 'title');
        $questions = Arr::get($section, 'questions');

        if (! is_array($questions) || $questions === []) {
            throw AssessmentGenerationException::invalidOutput("section {$title} must include questions.");
        }

        return [
            'title' => $title,
            'description' => $this->nullableGeneratedString($section, 'description'),
            'duration_minutes' => $this->nullableGeneratedInteger($section, 'duration_minutes', 1, 600),
            'scoring_mode' => $this->generatedEnumString($section, 'scoring_mode', ['weighted', 'points', 'percentage'], 'weighted'),
            'weight' => $this->generatedInteger($section, 'weight', 100, 1, 1000),
            'sort_order' => $this->generatedInteger($section, 'sort_order', ($index + 1) * 10, 0, 100000),
            'questions' => collect($questions)
                ->values()
                ->map(fn (mixed $question, int $questionIndex): array => $this->normalizeGeneratedQuestion($question, $questionIndex))
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     */
    private function persistDrafts(Campaign $campaign, array $sections, array $options): int
    {
        $createdQuestions = 0;

        $campaign->update([
            'status' => CampaignStatus::QuestionReview,
            'activated_at' => null,
        ]);

        foreach ($sections as $section) {
            $campaignSection = $campaign->sections()->create(Arr::except($section, ['questions']));

            foreach ($section['questions'] as $question) {
                $campaignSection->questions()->create([
                    ...$question,
                    'campaign_id' => $campaign->id,
                    'ai_generated' => true,
                    'status' => QuestionStatus::Draft,
                    'is_required' => true,
                ]);

                $createdQuestions++;
            }
        }

        $audit = $campaign->ai_generation_audit ?? [];
        $audit[] = $this->generationAuditEntry($options, $createdQuestions, count($sections));

        $campaign->update([
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
     *     sections_created: int,
     *     questions_created: int,
     * }
     */
    private function generationAuditEntry(array $options, int $questionsCreated, int $sectionsCreated): array
    {
        return [
            ...$this->generationAuditBase(AssessmentGeneratorAgent::class, $options),
            'sections_created' => $sectionsCreated,
            'questions_created' => $questionsCreated,
        ];
    }

    private function prompt(Campaign $campaign, array $options): string
    {
        return $this->encodePrompt($this->promptPayload($campaign, $options));
    }

    private function generationNotes(): string
    {
        return File::get(base_path('prompt/campaign-assessment-generation.txt'));
    }

    private function ensureCampaignTeamIsWritable(Campaign $campaign, int $expectedTeamId, bool $lockTeam = false): void
    {
        $teamQuery = $campaign->team()->whereKey($expectedTeamId)->where('status', TeamStatus::Active);

        if ($lockTeam) {
            $teamQuery->lockForUpdate();
        }

        if ($campaign->team_id !== $expectedTeamId || $teamQuery->first() === null) {
            throw new AssessmentGenerationException('The Campaign Team is no longer writable.');
        }
    }
}
