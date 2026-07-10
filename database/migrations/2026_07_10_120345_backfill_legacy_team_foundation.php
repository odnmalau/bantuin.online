<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $administratorCount = DB::table('users')->where('role', 'admin')->count();
            $campaignCount = DB::table('campaigns')->count();

            if ($administratorCount === 0 && $campaignCount === 0) {
                return;
            }

            if ($administratorCount !== 1) {
                throw new RuntimeException("Team backfill requires exactly one legacy administrator; found {$administratorCount}.");
            }

            if ($campaignCount !== 4) {
                throw new RuntimeException("Team backfill requires exactly four legacy Campaigns; found {$campaignCount}.");
            }

            $administrator = DB::table('users')->where('role', 'admin')->sole();
            $administratorName = trim((string) $administrator->name);

            if ($administratorName === '') {
                throw new RuntimeException('Team backfill requires the legacy administrator to have a name.');
            }

            $teamName = "{$administratorName}'s Team";

            if (mb_strlen($teamName) > 255) {
                throw new RuntimeException('Team backfill cannot derive a Team name within 255 characters.');
            }

            $invalidCampaignCount = DB::table('campaigns')
                ->whereNull('created_by')
                ->orWhere('created_by', '!=', $administrator->id)
                ->count();

            if ($invalidCampaignCount > 0) {
                throw new RuntimeException("Team backfill found {$invalidCampaignCount} Campaign(s) not owned by the legacy administrator.");
            }

            if (DB::table('assessments')->whereNull('campaign_id')->exists()) {
                throw new RuntimeException('Team backfill found an Assessment without a Campaign.');
            }

            $administratorEmail = mb_strtolower(trim((string) $administrator->email));
            $hasAdministratorCandidateHistory = DB::table('campaign_invitations')
                ->where('user_id', $administrator->id)
                ->orWhereRaw('LOWER(email) = ?', [$administratorEmail])
                ->exists()
                || DB::table('assessments')->where('user_id', $administrator->id)->exists()
                || DB::table('exam_sessions')->where('user_id', $administrator->id)->exists();

            if ($hasAdministratorCandidateHistory) {
                throw new RuntimeException('Team backfill cannot make the legacy administrator an Owner because Candidate history exists in the same Team.');
            }

            $now = now();
            $teamId = DB::table('teams')->insertGetId([
                'name' => $teamName,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('team_memberships')->insert([
                'team_id' => $teamId,
                'user_id' => $administrator->id,
                'role' => 'owner',
                'started_at' => $now,
                'last_used_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('platform_operator_authorities')->insert([
                'user_id' => $administrator->id,
                'granted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $administrator->id)->update([
                'current_team_id' => $teamId,
                'updated_at' => $now,
            ]);

            DB::table('campaigns')->update([
                'team_id' => $teamId,
                'updated_at' => $now,
            ]);

            DB::table('team_activities')->insert([
                'team_id' => $teamId,
                'actor_id' => $administrator->id,
                'actor_context' => 'system',
                'action' => 'team.migrated',
                'subject_type' => 'team',
                'subject_id' => $teamId,
                'after_state' => json_encode([
                    'status' => 'active',
                    'campaigns_attached' => $campaignCount,
                    'in_progress_exam_sessions' => DB::table('exam_sessions')
                        ->where('status', 'in_progress')
                        ->count(),
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This production data migration is intentionally forward-only.
    }
};
