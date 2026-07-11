<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TeamActivityRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        Team $team,
        User $actor,
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
    ): TeamActivity {
        return TeamActivity::query()->create([
            'team_id' => $team->id,
            'actor_id' => $actor->id,
            'actor_context' => 'team_member',
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_state' => $before,
            'after_state' => $after,
            'occurred_at' => now(),
        ]);
    }
}
