<?php

namespace App\Services;

use App\ExamSessionStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\ExamSession;
use App\Models\Team;
use App\Models\User;
use App\QuestionStatus;
use App\TeamStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamSessionService
{
    public function __construct(
        private ExamSessionFinalizer $finalizer,
    ) {}

    public function findActiveSession(User $user, Campaign $campaign): ?ExamSession
    {
        return ExamSession::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($campaign)
            ->where('status', ExamSessionStatus::InProgress)
            ->first();
    }

    public function startSession(User $user, Campaign $campaign): ExamSession
    {
        return DB::transaction(function () use ($user, $campaign): ExamSession {
            $team = Team::query()->whereKey($campaign->team_id)->lockForUpdate()->firstOrFail();

            if ($team->status !== TeamStatus::Active) {
                throw ValidationException::withMessages([
                    'session' => __('This Team is not accepting new Exam Sessions.'),
                ]);
            }

            if ($user->assessments()->whereBelongsTo($campaign)->exists()) {
                throw ValidationException::withMessages([
                    'session' => __('You have already submitted your assessment for this campaign.'),
                ]);
            }

            $existing = $this->findActiveSession($user, $campaign);

            if ($existing !== null) {
                $this->syncSectionExpiry($existing, $campaign);

                return $existing->fresh();
            }

            $sections = $this->orderedExamSections($campaign);

            if ($sections->isEmpty()) {
                throw ValidationException::withMessages([
                    'session' => __('There are no approved campaign questions available.'),
                ]);
            }

            $firstSection = $sections->first();

            return ExamSession::query()->create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'status' => ExamSessionStatus::InProgress,
                'current_section_id' => $firstSection->id,
                'current_section_started_at' => now(),
                'current_section_expires_at' => $this->sectionExpiresAt($firstSection),
                'completed_section_ids' => [],
                'warning_count' => 0,
                'integrity_events' => [],
                'answer_drafts' => [],
            ]);
        });
    }

    /**
     * @param  array<int|string, string>  $answers
     */
    public function saveCurrentSectionAnswers(ExamSession $session, Campaign $campaign, array $answers): ExamSession
    {
        $this->assertSessionActive($session, $campaign);
        $result = $this->syncSectionExpiry($session, $campaign);

        if ($result['finalized'] || $result['advanced']) {
            return $session->fresh();
        }

        $section = $this->currentSectionOrFail($session, $campaign);
        $questions = $this->approvedQuestionsForSection($section);
        $drafts = $session->answer_drafts ?? [];

        foreach ($questions as $question) {
            $key = (string) $question->id;

            if (array_key_exists($key, $answers)) {
                $drafts[$key] = $answers[$key];
            }
        }

        $session->update([
            'answer_drafts' => $drafts,
        ]);

        return $session->fresh();
    }

    /**
     * @return array{session: ExamSession, completed: bool}
     */
    public function advanceSection(ExamSession $session, Campaign $campaign): array
    {
        $this->assertSessionActive($session, $campaign);
        $result = $this->syncSectionExpiry($session, $campaign);

        if ($result['finalized']) {
            return [
                'session' => $session->fresh(),
                'completed' => true,
            ];
        }

        if ($result['advanced']) {
            $session = $session->fresh();

            return [
                'session' => $session,
                'completed' => $session->current_section_id === null,
            ];
        }

        $this->assertCurrentSectionNotExpired($session);

        $section = $this->currentSectionOrFail($session, $campaign);
        $this->assertSectionAnswersComplete($session, $section);

        $allSectionsComplete = $this->completeSectionAndAdvance($session, $campaign, $section);

        return [
            'session' => $session->fresh(),
            'completed' => $allSectionsComplete,
        ];
    }

    /**
     * @return array{session: ExamSession, auto_submitted: bool, assessment: Assessment|null}
     */
    public function recordViolation(ExamSession $session, Campaign $campaign, string $type): array
    {
        $this->assertSessionActive($session, $campaign);

        $events = $session->integrity_events ?? [];
        $events[] = [
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
        ];

        $warningCount = $session->warning_count + 1;
        $maxWarnings = $this->maxIntegrityWarnings();
        $autoSubmit = (bool) config('assessment.secure_exam.auto_submit_on_max_warnings', true);

        $session->update([
            'integrity_events' => $events,
            'warning_count' => $warningCount,
        ]);

        $session = $session->fresh();

        if ($autoSubmit && $warningCount >= $maxWarnings) {
            $assessment = $this->finalizeSession(
                $session,
                $campaign,
                submissionReason: 'integrity_max_warnings',
                status: ExamSessionStatus::AutoSubmitted,
                allowIncompleteAnswers: true,
            );

            return [
                'session' => $session->fresh(),
                'auto_submitted' => true,
                'assessment' => $assessment,
            ];
        }

        return [
            'session' => $session,
            'auto_submitted' => false,
            'assessment' => null,
        ];
    }

    public function finalizeSession(
        ExamSession $session,
        Campaign $campaign,
        ?UploadedFile $resume = null,
        ?string $submissionReason = null,
        ExamSessionStatus $status = ExamSessionStatus::Finalized,
        bool $allowIncompleteAnswers = false,
    ): Assessment {
        return $this->finalizer->finalize(
            session: $session,
            campaign: $campaign,
            resume: $resume,
            submissionReason: $submissionReason,
            status: $status,
            allowIncompleteAnswers: $allowIncompleteAnswers,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionPayloadForInertia(ExamSession $session, Campaign $campaign): array
    {
        $this->syncSectionExpiry($session, $campaign);
        $session = $session->fresh();

        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'current_section_id' => $session->current_section_id,
            'current_section_started_at' => $session->current_section_started_at,
            'current_section_expires_at' => $session->current_section_expires_at,
            'completed_section_ids' => $session->completed_section_ids ?? [],
            'warning_count' => $session->warning_count,
            'max_warnings' => $this->maxIntegrityWarnings(),
            'answer_drafts' => $session->answer_drafts ?? [],
            'ready_to_finalize' => $session->isActive()
                && $session->current_section_id === null
                && count($session->completed_section_ids ?? []) === $this->orderedExamSections($campaign)->count(),
            'secure_exam' => [
                'require_fullscreen' => (bool) config('assessment.secure_exam.require_fullscreen', true),
                'block_copy_paste' => (bool) config('assessment.secure_exam.block_copy_paste', true),
            ],
        ];
    }

    /**
     * @return Collection<int, CampaignSection>
     */
    public function orderedExamSections(Campaign $campaign): Collection
    {
        $campaign->loadMissing([
            'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'sections.questions' => fn ($query) => $query
                ->where('status', QuestionStatus::Approved->value)
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return $campaign->sections
            ->filter(fn (CampaignSection $section): bool => $section->questions->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<int, CampaignQuestion>
     */
    public function approvedCampaignQuestions(Campaign $campaign): Collection
    {
        return $this->orderedExamSections($campaign)
            ->flatMap(fn (CampaignSection $section) => $section->questions)
            ->values();
    }

    /**
     * Past section timers advance a complete section, or auto-finalize the Exam Session
     * when answers are incomplete. Incomplete expiry never soft-locks the session.
     *
     * @return array{advanced: bool, finalized: bool, assessment: ?Assessment}
     */
    public function syncSectionExpiry(ExamSession $session, Campaign $campaign): array
    {
        $noop = [
            'advanced' => false,
            'finalized' => false,
            'assessment' => null,
        ];

        if (! $session->isActive() || $session->current_section_id === null) {
            return $noop;
        }

        if (! (bool) config('assessment.secure_exam.enforce_section_timers', true)) {
            return $noop;
        }

        $expiresAt = $session->current_section_expires_at;

        if ($expiresAt === null || $expiresAt->isFuture()) {
            return $noop;
        }

        $section = $this->currentSectionOrFail($session, $campaign);

        if ($this->sectionAnswersAreComplete($session, $section)) {
            $this->completeSectionAndAdvance($session, $campaign, $section);

            return [
                'advanced' => true,
                'finalized' => false,
                'assessment' => null,
            ];
        }

        $assessment = $this->finalizeSession(
            $session,
            $campaign,
            submissionReason: 'section_timer_expired',
            status: ExamSessionStatus::AutoSubmitted,
            allowIncompleteAnswers: true,
        );

        return [
            'advanced' => false,
            'finalized' => true,
            'assessment' => $assessment,
        ];
    }

    /**
     * Marks the section complete and moves the session to the next section, or clears the active section when finished.
     *
     * @return bool Whether every section is now complete.
     */
    private function completeSectionAndAdvance(ExamSession $session, Campaign $campaign, CampaignSection $section): bool
    {
        $completed = collect($session->completed_section_ids ?? [])
            ->push($section->id)
            ->unique()
            ->values()
            ->all();

        $sections = $this->orderedExamSections($campaign);
        $currentIndex = $sections->search(fn (CampaignSection $item): bool => $item->id === $section->id);

        if ($currentIndex === false) {
            throw ValidationException::withMessages([
                'section' => __('The current section is no longer valid for this exam.'),
            ]);
        }

        $nextSection = $sections->get($currentIndex + 1);

        if ($nextSection === null) {
            $session->update([
                'completed_section_ids' => $completed,
                'current_section_id' => null,
                'current_section_started_at' => null,
                'current_section_expires_at' => null,
            ]);

            return true;
        }

        $session->update([
            'completed_section_ids' => $completed,
            'current_section_id' => $nextSection->id,
            'current_section_started_at' => now(),
            'current_section_expires_at' => $this->sectionExpiresAt($nextSection),
        ]);

        return false;
    }

    private function maxIntegrityWarnings(): int
    {
        return (int) config('assessment.secure_exam.max_integrity_warnings', 3);
    }

    private function sectionExpiresAt(CampaignSection $section): ?Carbon
    {
        if ($section->duration_minutes === null || $section->duration_minutes <= 0) {
            return null;
        }

        return Carbon::now()->addMinutes($section->duration_minutes);
    }

    private function assertSessionActive(ExamSession $session, Campaign $campaign): void
    {
        if ($session->campaign_id !== $campaign->id || ! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => __('This exam session is no longer active.'),
            ]);
        }
    }

    private function assertCurrentSectionNotExpired(ExamSession $session): void
    {
        if (! (bool) config('assessment.secure_exam.enforce_section_timers', true)) {
            return;
        }

        if ($session->current_section_expires_at !== null && $session->current_section_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'section' => __('Time expired for this section.'),
            ]);
        }
    }

    private function currentSectionOrFail(ExamSession $session, Campaign $campaign): CampaignSection
    {
        $sectionId = $session->current_section_id;

        if ($sectionId === null) {
            throw ValidationException::withMessages([
                'section' => __('No active section is available for this exam session.'),
            ]);
        }

        $section = $this->orderedExamSections($campaign)
            ->firstWhere('id', $sectionId);

        if ($section === null) {
            throw ValidationException::withMessages([
                'section' => __('The current section is no longer valid for this exam.'),
            ]);
        }

        return $section;
    }

    /**
     * @return Collection<int, CampaignQuestion>
     */
    private function approvedQuestionsForSection(CampaignSection $section): Collection
    {
        $section->loadMissing([
            'questions' => fn ($query) => $query
                ->where('status', QuestionStatus::Approved->value)
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return $section->questions->values();
    }

    private function sectionAnswersAreComplete(ExamSession $session, CampaignSection $section): bool
    {
        $drafts = $session->answer_drafts ?? [];

        foreach ($this->approvedQuestionsForSection($section) as $question) {
            $answer = $drafts[(string) $question->id] ?? null;

            if (! is_string($answer) || trim($answer) === '') {
                return false;
            }
        }

        return true;
    }

    private function assertSectionAnswersComplete(ExamSession $session, CampaignSection $section): void
    {
        $drafts = $session->answer_drafts ?? [];
        $errors = [];

        foreach ($this->approvedQuestionsForSection($section) as $question) {
            $answer = $drafts[(string) $question->id] ?? null;

            if (! is_string($answer) || trim($answer) === '') {
                $errors["answers.{$question->id}"] = __('Please answer this question.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
