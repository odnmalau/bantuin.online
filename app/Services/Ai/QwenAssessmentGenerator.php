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
use App\Services\CampaignSectionDistribution;
use App\TeamStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class QwenAssessmentGenerator
{
    use ConfiguresQwenAssessmentAgent;
    use LimitsGeneratedQuestionCount;
    use NormalizesGeneratedQuestions;

    public function __construct(
        private CampaignLifecycleService $lifecycle,
        private CampaignSectionDistribution $distribution,
    ) {}

    /**
     * Generate draft sections and questions for a campaign.
     *
     * @param  array{question_count?: int, difficulty?: string, question_mix?: string|null}  $options
     */
    public function generate(Campaign $campaign, array $options): int
    {
        return $this->generateDrafts($campaign, $options);
    }

    /**
     * Generate draft questions for an existing campaign section.
     *
     * @param  array{question_count?: int, difficulty?: string, question_mix?: string|null}  $options
     */
    public function generateForSection(Campaign $campaign, CampaignSection $section, array $options): int
    {
        if ($section->campaign_id !== $campaign->id) {
            throw new AssessmentGenerationException('The selected section does not belong to this campaign.');
        }

        return $this->generateDrafts($campaign, $options, $section);
    }

    /**
     * @param  array{question_count?: int, difficulty?: string, question_mix?: string|null}  $options
     */
    private function generateDrafts(
        Campaign $campaign,
        array $options,
        ?CampaignSection $targetSection = null,
    ): int {
        $expectedTeamId = $campaign->team_id;
        $this->ensureCampaignTeamIsWritable($campaign, $expectedTeamId);
        $this->ensureNoActiveDrafts($campaign);

        $generationOptions = [
            ...Arr::only($options, ['question_count', 'difficulty', 'question_mix']),
            'language' => $campaign->language ?? 'English',
        ];

        $campaign->loadMissing([
            'sections.questions',
        ]);

        $this->assertQwenApiKeyConfigured();

        $prompt = $this->prompt($campaign, $generationOptions, $targetSection);

        $response = $this->promptStructuredAgent(
            new AssessmentGeneratorAgent,
            $prompt,
            AssessmentGenerationException::class,
        );

        $sections = $this->normalizeSections($response->toArray());
        $sections = $this->limitSectionsToQuestionCount(
            $sections,
            max(1, (int) ($generationOptions['question_count'] ?? 6)),
        );

        return DB::transaction(function () use ($campaign, $expectedTeamId, $sections, $generationOptions, $targetSection): int {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->lifecycle->assertDefinitionEditable($lockedCampaign);
            $this->ensureCampaignTeamIsWritable($lockedCampaign, $expectedTeamId, true);
            $this->ensureNoActiveDrafts($lockedCampaign);

            $lockedTargetSection = $targetSection === null
                ? null
                : CampaignSection::query()
                    ->whereBelongsTo($lockedCampaign)
                    ->whereKey($targetSection->id)
                    ->lockForUpdate()
                    ->first();

            if ($targetSection !== null && $lockedTargetSection === null) {
                throw new AssessmentGenerationException('The selected section is no longer available.');
            }

            return $this->persistDrafts(
                $lockedCampaign,
                $sections,
                $generationOptions,
                $lockedTargetSection,
            );
        });
    }

    /**
     * Build the prompt payload sent to Qwen.
     *
     * @param  array{question_count?: int, language?: string, difficulty?: string, question_mix?: string|null}  $options
     * @return array<string, mixed>
     */
    public function promptPayload(
        Campaign $campaign,
        array $options,
        ?CampaignSection $targetSection = null,
    ): array {
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
                'language' => $campaign->language ?? 'English',
                'difficulty' => (string) ($options['difficulty'] ?? 'mixed'),
                'question_mix' => $options['question_mix'] ?? null,
            ],
            'allowed_question_types' => QuestionType::selectOptions(),
            'target_section' => $targetSection === null ? null : [
                'title' => $targetSection->title,
                'description' => $targetSection->description,
            ],
            'existing_content_to_avoid' => $campaign->sections
                ->map(fn (CampaignSection $section): array => [
                    'section_title' => $section->title,
                    'section_description' => $section->description,
                    'questions' => $section->questions
                        ->map(fn ($question): array => [
                            'type' => $question->type->value,
                            'prompt' => $question->prompt,
                            'expected_rubric' => $question->expected_rubric,
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
            'weight' => $this->generatedInteger($section, 'weight', 100, 1, 100),
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
    private function persistDrafts(
        Campaign $campaign,
        array $sections,
        array $options,
        ?CampaignSection $targetSection = null,
    ): int {
        $createdQuestions = 0;

        $campaign->update([
            'status' => CampaignStatus::QuestionReview,
            'activated_at' => null,
        ]);

        if ($targetSection !== null) {
            $nextSortOrder = ((int) $targetSection->questions()->max('sort_order')) + 10;

            foreach (collect($sections)->flatMap(fn (array $section): array => $section['questions']) as $question) {
                $targetSection->questions()->create([
                    ...$question,
                    'campaign_id' => $campaign->id,
                    'ai_generated' => true,
                    'status' => QuestionStatus::Draft,
                    'is_required' => true,
                    'sort_order' => $nextSortOrder,
                ]);

                $createdQuestions++;
                $nextSortOrder += 10;
            }
        } else {
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

            $this->distribution->normalize($campaign);
        }

        $audit = $campaign->ai_generation_audit ?? [];
        $audit[] = $this->generationAuditEntry(
            $options,
            $createdQuestions,
            $targetSection === null ? count($sections) : 0,
            $targetSection,
        );

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
     *     target_section_id?: int,
     * }
     */
    private function generationAuditEntry(
        array $options,
        int $questionsCreated,
        int $sectionsCreated,
        ?CampaignSection $targetSection = null,
    ): array {
        return [
            ...$this->generationAuditBase(AssessmentGeneratorAgent::class, $options),
            'sections_created' => $sectionsCreated,
            'questions_created' => $questionsCreated,
            ...($targetSection === null ? [] : ['target_section_id' => $targetSection->id]),
        ];
    }

    private function prompt(
        Campaign $campaign,
        array $options,
        ?CampaignSection $targetSection = null,
    ): string {
        return $this->encodePrompt($this->promptPayload($campaign, $options, $targetSection));
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

    private function ensureNoActiveDrafts(Campaign $campaign): void
    {
        if ($campaign->questions()->where('status', QuestionStatus::Draft->value)->exists()) {
            throw new AssessmentGenerationException('Review or discard existing draft questions before generating another assessment.');
        }
    }
}
