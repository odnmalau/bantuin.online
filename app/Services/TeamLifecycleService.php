<?php

namespace App\Services;

use App\ExamSessionStatus;
use App\Models\ExamSession;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\TeamInvitationStatus;
use App\TeamStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamLifecycleService
{
    public function __construct(private TeamActivityRecorder $activities) {}

    public function deactivate(Team $team, User $actor): Team
    {
        return DB::transaction(function () use ($team, $actor): Team {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($lockedTeam, $actor);

            if ($lockedTeam->status !== TeamStatus::Active) {
                throw ValidationException::withMessages(['team' => __('This Team is already deactivated.')]);
            }

            $hasInProgressExam = ExamSession::query()
                ->where('status', ExamSessionStatus::InProgress)
                ->whereHas('campaign', fn ($query) => $query->where('team_id', $lockedTeam->id))
                ->exists();

            if ($hasInProgressExam) {
                throw ValidationException::withMessages([
                    'team' => __('This Team cannot be deactivated while an exam is in progress.'),
                ]);
            }

            $lockedTeam->update([
                'status' => TeamStatus::Deactivated,
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
            ]);

            $this->activities->record(
                $lockedTeam,
                $actor,
                'team_deactivated',
                $lockedTeam,
                before: ['status' => TeamStatus::Active->value],
                after: ['status' => TeamStatus::Deactivated->value],
            );

            return $lockedTeam;
        });
    }

    public function reactivate(Team $team, User $actor): Team
    {
        return DB::transaction(function () use ($team, $actor): Team {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($lockedTeam, $actor);

            if ($lockedTeam->status !== TeamStatus::Deactivated) {
                throw ValidationException::withMessages(['team' => __('This Team is already active.')]);
            }

            $lockedTeam->update([
                'status' => TeamStatus::Active,
                'deactivated_at' => null,
                'deactivated_by' => null,
            ]);

            $this->activities->record(
                $lockedTeam,
                $actor,
                'team_reactivated',
                $lockedTeam,
                before: ['status' => TeamStatus::Deactivated->value],
                after: ['status' => TeamStatus::Active->value],
            );

            return $lockedTeam;
        });
    }

    public function deleteEmpty(Team $team, User $actor): void
    {
        DB::transaction(function () use ($team, $actor): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $ownerMembership = $this->assertOwner($lockedTeam, $actor);

            if ($blocker = $this->emptyTeamDeletionBlocker($lockedTeam, $ownerMembership->id)) {
                throw ValidationException::withMessages(['team' => $blocker]);
            }

            $this->activities->record(
                $lockedTeam,
                $actor,
                'team_deleted',
                $lockedTeam,
                before: ['name' => $lockedTeam->name, 'status' => $lockedTeam->status->value],
            );

            User::query()
                ->where('current_team_id', $lockedTeam->id)
                ->lockForUpdate()
                ->get()
                ->each(fn (User $user) => $user->replaceCurrentTeamAfterMembershipEnds($lockedTeam->id));

            $lockedTeam->delete();
        });
    }

    public function emptyTeamDeletionBlocker(Team $team, ?int $ownerMembershipId = null): ?string
    {
        if ($team->campaigns()->exists()) {
            return __('Teams with Campaign history cannot be deleted. Deactivate this Team to retain readable history.');
        }

        if ($team->invitations()->where('status', TeamInvitationStatus::Pending)->exists()) {
            return __('Revoke pending Team Invitations before deleting this Team.');
        }

        $ownerMembershipId ??= $team->ownerMembership()->value('id');

        if ($team->memberships()->whereKeyNot($ownerMembershipId)->exists()) {
            return __('Teams with current or ended non-owner membership history cannot be deleted.');
        }

        return null;
    }

    private function assertOwner(Team $team, User $actor): TeamMembership
    {
        $ownerMembership = $team->ownerMembership()->lockForUpdate()->first();

        if ($ownerMembership?->user_id !== $actor->id) {
            throw new AuthorizationException;
        }

        return $ownerMembership;
    }
}
