<?php

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Team;
use App\Models\User;
use App\TeamMembershipRole;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated experience exposes valid current team identity', function () {
    $team = Team::factory()->create(['name' => 'Hiring Team']);
    $user = User::factory()
        ->teamAdministrator($team)
        ->withCurrentTeam($team)
        ->platformOperator()
        ->create();
    $candidateCampaign = Campaign::factory()->active()->create();
    CampaignInvitation::factory()->for($candidateCampaign)->accepted($user)->create();

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.currentTeam', [
                'id' => $team->id,
                'name' => 'Hiring Team',
                'status' => 'active',
                'role' => TeamMembershipRole::Administrator->value,
            ])
            ->where('auth.platformOperator', true)
            ->where('auth.capabilities.candidateWork', true)
            ->where('locale', 'en-US')
            ->where('timeZone', config('app.timezone'))
            ->missing('auth.user.role'));
});

test('authenticated experience rejects a stale current team selection', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.currentTeam', null)
            ->where('auth.capabilities.candidateWork', false)
            ->where('auth.platformOperator', false));
});
