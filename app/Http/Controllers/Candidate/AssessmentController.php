<?php

namespace App\Http\Controllers\Candidate;

use App\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\SubmitAssessmentRequest;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionStatus;
use App\QuestionType;
use App\Services\AssessmentEventRecorder;
use App\Services\CampaignInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(private CampaignInvitationService $invitations) {}

    /**
     * Redirect legacy exam entry to a single accessible campaign when possible.
     */
    public function redirectExam(Request $request): RedirectResponse|Response
    {
        $accessibleCampaigns = $this->invitations->accessibleCampaignsForUser($request->user());

        if ($accessibleCampaigns->count() === 1) {
            return redirect()->route('candidate.campaigns.exam', $accessibleCampaigns->first());
        }

        return $this->renderExam($request, null);
    }

    /**
     * Show the candidate exam form for an assigned campaign.
     */
    public function campaignExam(Request $request, Campaign $campaign): Response
    {
        $campaign = $this->accessibleCampaignForExam($request, $campaign);

        return $this->renderExam($request, $campaign);
    }

    /**
     * Store a submitted assessment for an assigned campaign.
     */
    public function store(
        SubmitAssessmentRequest $request,
        Campaign $campaign,
        AssessmentEventRecorder $events,
    ): RedirectResponse {
        $campaign = $this->accessibleCampaignForExam($request, $campaign);

        if ($request->user()->assessments()->whereBelongsTo($campaign)->exists()) {
            throw ValidationException::withMessages([
                'assessment' => __('You have already submitted your assessment for this campaign.'),
            ]);
        }

        $questions = $this->approvedCampaignQuestions($campaign);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'assessment' => __('There are no approved campaign questions available.'),
            ]);
        }

        $answers = collect($request->validated('answers'))
            ->mapWithKeys(fn (string $answer, string|int $questionId): array => [(string) $questionId => $answer]);
        $errors = [];

        foreach ($questions as $question) {
            if (! $answers->has((string) $question->id)) {
                $errors["answers.{$question->id}"] = __('Please answer this question.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $resume = $request->file('resume');
        $resumePath = $resume?->store('resumes', 'local');

        if (! is_string($resumePath)) {
            throw ValidationException::withMessages([
                'resume' => __('The resume could not be stored. Please try again.'),
            ]);
        }

        $assessment = Assessment::query()->create([
            'user_id' => $request->user()->id,
            'campaign_id' => $campaign->id,
            'resume_path' => $resumePath,
            'resume_original_name' => $resume?->getClientOriginalName(),
            'answers_payload' => $questions
                ->map(fn (CampaignQuestion $question) => $this->answerSnapshotEntry(
                    $question,
                    $answers->get((string) $question->id),
                ))
                ->values()
                ->all(),
            'status' => AssessmentStatus::Submitted,
        ]);

        $events->record(
            assessment: $assessment,
            type: 'candidate_submitted',
            title: __('Candidate submitted assessment'),
            description: __('Candidate completed the assessment form.'),
            payload: [
                'campaign_id' => $campaign->id,
                'question_count' => $questions->count(),
            ],
            actor: $request->user(),
        );

        $events->record(
            assessment: $assessment,
            type: 'resume_uploaded',
            title: __('Resume uploaded'),
            description: __('Candidate uploaded a resume PDF for screening.'),
            payload: [
                'original_name' => $assessment->resume_original_name,
                'size_kb' => (int) ceil($resume->getSize() / 1024),
            ],
            actor: $request->user(),
        );

        Bus::chain([
            new ScreenResumeWithAi($assessment),
            new EvaluateAssessmentWithAi($assessment),
        ])->dispatch();

        $events->record(
            assessment: $assessment,
            type: 'assessment_queued',
            title: __('Assessment queued for AI processing'),
            description: __('Resume screening and assessment evaluation jobs were queued.'),
            payload: [
                'jobs' => [
                    ScreenResumeWithAi::class,
                    EvaluateAssessmentWithAi::class,
                ],
            ],
        );

        return to_route('candidate.assessments.show', $assessment);
    }

    /**
     * Show an assessment status page.
     */
    public function show(Request $request, Assessment $assessment): Response
    {
        abort_unless($assessment->user_id === $request->user()->id, 403);

        $assessment->loadMissing('campaign:id,title,role_title');

        return Inertia::render('candidate/assessments/show', [
            'assessment' => [
                'id' => $assessment->id,
                'campaign' => $this->campaignSummaryForAssessment($assessment),
                'campaign_id' => $assessment->campaign_id,
                'answers_payload' => collect($assessment->answers_payload ?? [])
                    ->map(fn (mixed $answer): array => $this->answerSnapshotForCandidate($answer))
                    ->all(),
                'resume_original_name' => $assessment->resume_original_name,
                'resume_score' => $assessment->resume_score,
                'ai_score' => $assessment->ai_score,
                'ai_justification' => $assessment->ai_justification,
                'status' => $assessment->status->value,
                'created_at' => $assessment->created_at,
                'evaluated_at' => $assessment->evaluated_at,
                'email_sent_at' => $assessment->email_sent_at,
            ],
        ]);
    }

    private function renderExam(Request $request, ?Campaign $campaign): Response
    {
        $sections = $this->sectionsForCandidate($campaign);
        $questions = collect($sections)
            ->flatMap(fn (array $section): array => $section['questions'])
            ->values()
            ->all();
        $assessment = $this->currentAssessmentForExam($request, $campaign);

        return Inertia::render('candidate/exam', [
            'campaign' => $this->campaignSummaryForExam($campaign),
            'sections' => $sections,
            'questions' => $questions,
            'assessment' => $this->assessmentSummaryForExam($assessment),
        ]);
    }

    private function accessibleCampaignForExam(Request $request, Campaign $campaign): Campaign
    {
        abort_unless($this->invitations->userCanAccessCampaignExam($request->user(), $campaign), 403);

        return $this->campaignForExam($campaign);
    }

    private function campaignForExam(Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->whereKey($campaign->id)
            ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Approved->value))
            ->with([
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.questions' => fn ($query) => $query
                    ->where('status', QuestionStatus::Approved->value)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();
    }

    /**
     * @return Collection<int, CampaignQuestion>
     */
    private function approvedCampaignQuestions(Campaign $campaign): Collection
    {
        return $campaign->sections
            ->flatMap(fn ($section) => $section->questions)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sectionsForCandidate(?Campaign $campaign): array
    {
        if ($campaign === null) {
            return [];
        }

        return $campaign->sections
            ->map(fn (CampaignSection $section): array => $this->sectionForCandidate($section))
            ->filter(fn (array $section): bool => $section['questions'] !== [])
            ->values()
            ->all();
    }

    private function currentAssessmentForExam(Request $request, ?Campaign $campaign): ?Assessment
    {
        if ($campaign === null) {
            return null;
        }

        return $request->user()
            ->assessments()
            ->whereBelongsTo($campaign)
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function answerSnapshotEntry(CampaignQuestion $question, ?string $answer): array
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
     * @return array{question_id: int|null, question: string, answer: string}
     */
    private function answerSnapshotForCandidate(mixed $answer): array
    {
        return [
            'question_id' => data_get($answer, 'question_id'),
            'question' => (string) data_get($answer, 'question', ''),
            'answer' => (string) data_get($answer, 'answer', ''),
        ];
    }

    /**
     * @return array{title: string, role_title: string}|null
     */
    private function campaignSummaryForAssessment(Assessment $assessment): ?array
    {
        if ($assessment->campaign === null) {
            return null;
        }

        return [
            'title' => $assessment->campaign->title,
            'role_title' => $assessment->campaign->role_title,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function campaignSummaryForExam(?Campaign $campaign): ?array
    {
        if ($campaign === null) {
            return null;
        }

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'role_title' => $campaign->role_title,
            'seniority' => $campaign->seniority,
            'threshold_score' => $campaign->threshold_score,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assessmentSummaryForExam(?Assessment $assessment): ?array
    {
        if ($assessment === null) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'created_at' => $assessment->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionForCandidate(CampaignQuestion $question): array
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

                    return $left !== '' && $right !== ''
                        ? ['left' => $left, 'right' => $right]
                        : null;
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

    /**
     * @return array<string, mixed>
     */
    private function sectionForCandidate(CampaignSection $section): array
    {
        $questions = $section->questions
            ->map(fn (CampaignQuestion $question): array => $this->questionForCandidate($question))
            ->values()
            ->all();

        return [
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'duration_minutes' => $section->duration_minutes,
            'sort_order' => $section->sort_order,
            'question_count' => count($questions),
            'questions' => $questions,
        ];
    }
}
