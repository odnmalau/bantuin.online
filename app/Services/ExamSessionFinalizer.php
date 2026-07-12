<?php

namespace App\Services;

use App\AssessmentStatus;
use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\ExamSessionStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Jobs\ScreenResumeWithAi;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamSessionFinalizer
{
    public function __construct(
        private AssessmentSubmissionBuilder $submissionBuilder,
        private AssessmentEventRecorder $events,
    ) {}

    public function finalize(
        ExamSession $session,
        Campaign $campaign,
        ?UploadedFile $resume = null,
        ?string $submissionReason = null,
        ExamSessionStatus $status = ExamSessionStatus::Finalized,
        bool $allowIncompleteAnswers = false,
    ): Assessment {
        [$assessment, $shouldQueueProcessing] = DB::transaction(function () use ($session, $campaign, $resume, $submissionReason, $status, $allowIncompleteAnswers): array {
            $lockedSession = ExamSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            Team::query()->whereKey($lockedCampaign->team_id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->campaign_id !== $lockedCampaign->id
                || $lockedCampaign->status !== CampaignStatus::Active
                || $lockedCampaign->team->status !== TeamStatus::Active
                || $lockedCampaign->invitations()
                    ->where('user_id', $lockedSession->user_id)
                    ->where('status', CampaignInvitationStatus::Accepted)
                    ->doesntExist()) {
                throw ValidationException::withMessages([
                    'session' => __('This exam session is no longer available.'),
                ]);
            }

            $shouldQueueProcessing = $lockedSession->isActive();
            $assessment = $this->finalizeLocked(
                $lockedSession,
                $lockedCampaign,
                $resume,
                $submissionReason,
                $status,
                $allowIncompleteAnswers,
            );

            return [$assessment, $shouldQueueProcessing];
        }, attempts: 3);

        if ($shouldQueueProcessing) {
            $this->queueAssessmentProcessing($assessment);
        }

        return $assessment;
    }

    private function finalizeLocked(
        ExamSession $session,
        Campaign $campaign,
        ?UploadedFile $resume,
        ?string $submissionReason,
        ExamSessionStatus $status,
        bool $allowIncompleteAnswers,
    ): Assessment {
        if (! $session->isActive()) {
            if ($session->assessment_id !== null) {
                return $session->assessment()->firstOrFail();
            }

            throw ValidationException::withMessages([
                'session' => __('This exam session is no longer active.'),
            ]);
        }

        $this->syncSectionExpiry($session, $campaign, $allowIncompleteAnswers);

        if (! $allowIncompleteAnswers) {
            $this->assertReadyToSubmit($session, $campaign);
        }

        $questions = $this->approvedCampaignQuestions($campaign);
        $drafts = $this->validatedDrafts($session, $questions, $allowIncompleteAnswers);

        if ($allowIncompleteAnswers) {
            $session->update([
                'answer_drafts' => $drafts,
            ]);
            $session->refresh();
        }

        $this->storeResume($session, $resume);
        $session->refresh();

        $allowsResumeLess = $this->allowsResumeLessAutoSubmit($submissionReason, $allowIncompleteAnswers);

        if ($session->resume_path === null && ! $allowsResumeLess) {
            throw ValidationException::withMessages([
                'resume' => __('Upload your resume PDF before submitting.'),
            ]);
        }

        if ($campaign->assessments()->where('user_id', $session->user_id)->exists()) {
            throw ValidationException::withMessages([
                'assessment' => __('You have already submitted your assessment for this campaign.'),
            ]);
        }

        $assessment = Assessment::query()->create([
            'user_id' => $session->user_id,
            'campaign_id' => $campaign->id,
            'resume_path' => $session->resume_path,
            'resume_original_name' => $session->resume_original_name,
            'answers_payload' => $this->submissionBuilder->buildAnswersPayload(
                $questions,
                $drafts,
            ),
            'status' => AssessmentStatus::Submitted,
        ]);

        $session->update([
            'assessment_id' => $assessment->id,
            'status' => $status,
            'submission_reason' => $submissionReason ?? 'candidate_submitted',
            'finalized_at' => now(),
        ]);

        $this->recordSubmissionEvents($assessment, $campaign, $session->fresh(), $questions, $submissionReason);

        return $assessment;
    }

    private function assertReadyToSubmit(ExamSession $session, Campaign $campaign): void
    {
        if ($session->current_section_id !== null) {
            $section = $this->currentSectionOrFail($session, $campaign);
            $this->assertSectionAnswersComplete($session, $section);

            throw ValidationException::withMessages([
                'session' => __('Advance through all sections before submitting the assessment.'),
            ]);
        }

        $sections = $this->orderedExamSections($campaign);
        $completedCount = count($session->completed_section_ids ?? []);

        if ($completedCount < $sections->count()) {
            throw ValidationException::withMessages([
                'session' => __('Complete every section before submitting the assessment.'),
            ]);
        }
    }

    /**
     * @param  Collection<int, CampaignQuestion>  $questions
     * @return array<int|string, string>
     */
    private function validatedDrafts(ExamSession $session, Collection $questions, bool $allowIncompleteAnswers): array
    {
        $drafts = $session->answer_drafts ?? [];
        $errors = [];

        foreach ($questions as $question) {
            $answer = $drafts[(string) $question->id] ?? null;

            if (! is_string($answer) || trim($answer) === '') {
                if ($allowIncompleteAnswers) {
                    $drafts[(string) $question->id] = '';

                    continue;
                }

                $errors["answers.{$question->id}"] = __('Please answer this question.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $drafts;
    }

    private function storeResume(ExamSession $session, ?UploadedFile $resume): void
    {
        if ($resume === null) {
            return;
        }

        $resumePath = $resume->store('resumes', 'local');

        if (! is_string($resumePath)) {
            throw ValidationException::withMessages([
                'resume' => __('The resume could not be stored. Please try again.'),
            ]);
        }

        $session->update([
            'resume_path' => $resumePath,
            'resume_original_name' => $resume->getClientOriginalName(),
        ]);
    }

    /**
     * @param  Collection<int, CampaignQuestion>  $questions
     */
    private function recordSubmissionEvents(
        Assessment $assessment,
        Campaign $campaign,
        ExamSession $session,
        Collection $questions,
        ?string $submissionReason,
    ): void {
        $user = $session->user;

        $this->events->record(
            assessment: $assessment,
            type: 'candidate_submitted',
            title: __('Candidate submitted assessment'),
            description: match ($submissionReason) {
                'integrity_max_warnings' => __('Assessment was auto-submitted after exceeding integrity warnings.'),
                'section_timer_expired' => __('Assessment was auto-submitted after a section timer expired.'),
                default => __('Candidate completed the assessment form.'),
            },
            payload: [
                'campaign_id' => $campaign->id,
                'question_count' => $questions->count(),
                'submission_reason' => $session->submission_reason,
                'warning_count' => $session->warning_count,
                'resume_uploaded' => filled($assessment->resume_path),
            ],
            actor: $user instanceof User ? $user : null,
        );

        if (filled($assessment->resume_path)) {
            $this->events->record(
                assessment: $assessment,
                type: 'resume_uploaded',
                title: __('Resume uploaded'),
                description: __('Candidate uploaded a resume PDF for screening.'),
                payload: [
                    'original_name' => $assessment->resume_original_name,
                ],
                actor: $user instanceof User ? $user : null,
            );
        }

        if ($session->warning_count > 0 || ($session->integrity_events ?? []) !== []) {
            $this->events->record(
                assessment: $assessment,
                type: 'exam_integrity_summary',
                title: __('Exam integrity summary'),
                description: __('Integrity warnings were recorded during the secure exam session.'),
                payload: [
                    'warning_count' => $session->warning_count,
                    'integrity_events' => $session->integrity_events ?? [],
                ],
            );
        }
    }

    private function queueAssessmentProcessing(Assessment $assessment): void
    {
        Bus::chain([
            new ScreenResumeWithAi($assessment),
            new EvaluateAssessmentWithAi($assessment),
        ])->dispatch();

        $this->events->record(
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
    }

    /**
     * @return Collection<int, CampaignQuestion>
     */
    private function approvedCampaignQuestions(Campaign $campaign): Collection
    {
        return $this->orderedExamSections($campaign)
            ->flatMap(fn (CampaignSection $section) => $section->questions)
            ->values();
    }

    private function syncSectionExpiry(ExamSession $session, Campaign $campaign, bool $allowIncompleteAnswers = false): void
    {
        if (! $session->isActive() || $session->current_section_id === null) {
            return;
        }

        if (! (bool) config('assessment.secure_exam.enforce_section_timers', true)) {
            return;
        }

        $expiresAt = $session->current_section_expires_at;

        if ($expiresAt === null || $expiresAt->isFuture()) {
            return;
        }

        $section = $this->currentSectionOrFail($session, $campaign);

        if ($this->sectionAnswersAreComplete($session, $section)) {
            $this->completeSectionAndAdvance($session, $campaign, $section);

            return;
        }

        // Incomplete auto-submit paths must not re-validate or soft-lock on expiry.
        if ($allowIncompleteAnswers) {
            return;
        }
    }

    private function allowsResumeLessAutoSubmit(?string $submissionReason, bool $allowIncompleteAnswers): bool
    {
        if (! $allowIncompleteAnswers) {
            return false;
        }

        return in_array($submissionReason, [
            'integrity_max_warnings',
            'section_timer_expired',
        ], true);
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

    /**
     * @return Collection<int, CampaignSection>
     */
    private function orderedExamSections(Campaign $campaign): Collection
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
            'current_section_expires_at' => $nextSection->duration_minutes === null || $nextSection->duration_minutes <= 0
                ? null
                : now()->addMinutes($nextSection->duration_minutes),
        ]);

        return false;
    }
}
