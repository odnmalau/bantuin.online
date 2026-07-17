<?php

namespace App\Services;

use App\CampaignInvitationStatus;
use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CandidateApplication;
use App\Models\ExamSession;
use App\Models\Team;
use App\Models\User;
use App\TeamStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CandidateApplicationService
{
    public function forUserCampaign(User $user, Campaign $campaign): ?CandidateApplication
    {
        return CandidateApplication::query()
            ->whereHas('invitation', function ($query) use ($user, $campaign): void {
                $query
                    ->where('campaign_id', $campaign->id)
                    ->acceptedForUser($user);
            })
            ->first();
    }

    public function storeResume(
        User $user,
        Campaign $campaign,
        UploadedFile $resume,
    ): CandidateApplication {
        DB::transaction(fn (): CampaignInvitation => $this->assertCanStoreResume($user, $campaign));

        $resumePath = $resume->store('resumes', 'r2-private');

        if (! is_string($resumePath)) {
            throw ValidationException::withMessages([
                'resume' => __('The resume could not be stored. Please try again.'),
            ]);
        }

        try {
            [$application, $previousPath] = DB::transaction(function () use ($user, $campaign, $resume, $resumePath): array {
                $invitation = $this->assertCanStoreResume($user, $campaign);
                $application = CandidateApplication::query()
                    ->whereBelongsTo($invitation, 'invitation')
                    ->lockForUpdate()
                    ->first();
                $previousPath = $application?->resume_path;

                $application ??= new CandidateApplication([
                    'campaign_invitation_id' => $invitation->id,
                ]);

                $application->fill([
                    'resume_path' => $resumePath,
                    'resume_original_name' => $resume->getClientOriginalName(),
                    'resume_uploaded_at' => now(),
                    'locked_at' => null,
                ])->save();

                return [$application->fresh(), $previousPath];
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->deleteStoredResume($resumePath);

            throw $exception;
        }

        if (is_string($previousPath) && $previousPath !== $resumePath) {
            $this->deleteStoredResume($previousPath);
        }

        return $application;
    }

    public function lockForExamStart(CampaignInvitation $invitation): CandidateApplication
    {
        $application = CandidateApplication::query()
            ->whereBelongsTo($invitation, 'invitation')
            ->lockForUpdate()
            ->first();

        if ($application === null) {
            throw ValidationException::withMessages([
                'resume' => __('Upload your resume PDF before starting the exam.'),
            ]);
        }

        if ($application->locked_at === null) {
            $application->update(['locked_at' => now()]);
        }

        return $application->fresh();
    }

    public function lockForFinalization(int $userId, Campaign $campaign): ?CandidateApplication
    {
        return CandidateApplication::query()
            ->whereHas('invitation', function ($query) use ($userId, $campaign): void {
                $query
                    ->where('campaign_id', $campaign->id)
                    ->where('user_id', $userId)
                    ->where('status', CampaignInvitationStatus::Accepted);
            })
            ->lockForUpdate()
            ->first();
    }

    /** @return array{resume_original_name: string, resume_uploaded_at: string, locked: bool} */
    public function payload(CandidateApplication $application): array
    {
        return [
            'resume_original_name' => $application->resume_original_name,
            'resume_uploaded_at' => $application->resume_uploaded_at->toISOString(),
            'locked' => $application->locked_at !== null,
        ];
    }

    private function assertCanStoreResume(User $user, Campaign $campaign): CampaignInvitation
    {
        $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
        $team = Team::query()->whereKey($lockedCampaign->team_id)->lockForUpdate()->firstOrFail();

        if ($lockedCampaign->status !== CampaignStatus::Active || $team->status !== TeamStatus::Active) {
            throw ValidationException::withMessages([
                'resume' => __('This Campaign is not accepting Candidate applications.'),
            ]);
        }

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $lockedCampaign->id)
            ->where('user_id', $user->id)
            ->where('status', CampaignInvitationStatus::Accepted)
            ->lockForUpdate()
            ->first();

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'resume' => __('You do not have an accepted invitation for this Campaign.'),
            ]);
        }

        if (ExamSession::query()->whereBelongsTo($user)->whereBelongsTo($lockedCampaign)->exists()) {
            throw ValidationException::withMessages([
                'resume' => __('Your resume is locked because the exam has already started.'),
            ]);
        }

        $application = CandidateApplication::query()
            ->whereBelongsTo($invitation, 'invitation')
            ->lockForUpdate()
            ->first();

        if ($application?->locked_at !== null) {
            throw ValidationException::withMessages([
                'resume' => __('Your resume is locked because the exam has already started.'),
            ]);
        }

        if ($lockedCampaign->assessments()->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages([
                'resume' => __('Your resume is locked because the assessment has already been submitted.'),
            ]);
        }

        return $invitation;
    }

    private function deleteStoredResume(string $path): void
    {
        try {
            Storage::disk('r2-private')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
