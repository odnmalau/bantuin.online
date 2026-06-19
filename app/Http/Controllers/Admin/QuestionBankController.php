<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ProvidesQuestionDifficultyOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionBankRequest;
use App\Http\Requests\Admin\UpdateQuestionBankRequest;
use App\Models\BankQuestion;
use App\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    use ProvidesQuestionDifficultyOptions;

    /**
     * Display the question libraries.
     */
    public function index(): Response
    {
        return Inertia::render('admin/question-banks/index', [
            'questionBanks' => QuestionBank::query()
                ->with('creator:id,name,email')
                ->withCount('questions')
                ->latest()
                ->get()
                ->map(fn (QuestionBank $questionBank): array => $this->questionBankSummary($questionBank)),
        ]);
    }

    /**
     * Show the form for creating a question library.
     */
    public function create(): Response
    {
        return Inertia::render('admin/question-banks/create', [
            'difficultyOptions' => $this->difficultyOptions(),
        ]);
    }

    /**
     * Store a newly created question library.
     */
    public function store(StoreQuestionBankRequest $request): RedirectResponse
    {
        $questionBank = QuestionBank::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question library created.')]);

        return to_route('admin.question-banks.show', $questionBank);
    }

    /**
     * Display the specified question library.
     */
    public function show(QuestionBank $questionBank): Response
    {
        $questionBank->loadMissing([
            'creator:id,name,email',
            'questions' => fn ($query) => $query
                ->withCount('campaignQuestions')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return Inertia::render('admin/question-banks/show', [
            'questionBank' => [
                ...$this->questionBankSummary($questionBank),
                'ai_generation_audit' => $questionBank->ai_generation_audit ?? [],
                'questions' => $questionBank->questions
                    ->map(fn (BankQuestion $question): array => $this->questionBankQuestionPayload($question)),
            ],
        ]);
    }

    /**
     * Show the form for editing a question library.
     */
    public function edit(QuestionBank $questionBank): Response
    {
        return Inertia::render('admin/question-banks/edit', [
            'questionBank' => $questionBank->only(['id', 'title', 'description', 'skill_area', 'difficulty', 'is_active']),
            'difficultyOptions' => $this->difficultyOptions(),
        ]);
    }

    /**
     * Update the specified question library.
     */
    public function update(UpdateQuestionBankRequest $request, QuestionBank $questionBank): RedirectResponse
    {
        $questionBank->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question library updated.')]);

        return to_route('admin.question-banks.show', $questionBank);
    }

    /**
     * Remove the specified question library.
     */
    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $questionBank->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question library deleted.')]);

        return to_route('admin.question-banks.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function questionBankSummary(QuestionBank $questionBank): array
    {
        return [
            'id' => $questionBank->id,
            'title' => $questionBank->title,
            'description' => $questionBank->description,
            'skill_area' => $questionBank->skill_area,
            'difficulty' => $questionBank->difficulty,
            'is_active' => $questionBank->is_active,
            'questions_count' => $questionBank->questions_count,
            'created_by' => $questionBank->creator?->name,
            'created_at' => $questionBank->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionBankQuestionPayload(BankQuestion $question): array
    {
        return [
            'id' => $question->id,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'grading_mode' => $question->grading_mode->value,
            'grading_mode_label' => $question->grading_mode->label(),
            'prompt' => $question->prompt,
            'options' => $question->options ?? [],
            'correct_answer' => $question->correct_answer ?? [],
            'expected_rubric' => $question->expected_rubric,
            'points' => $question->points,
            'difficulty' => $question->difficulty,
            'skill_tags' => $question->skill_tags ?? [],
            'ai_generated' => $question->ai_generated,
            'status' => $question->status->value,
            'status_label' => $question->status->label(),
            'sort_order' => $question->sort_order,
            'campaign_imports_count' => $question->campaign_questions_count,
        ];
    }
}
