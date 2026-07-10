<?php

use App\Models\Team;
use App\Models\User;
use App\TeamMembershipRole;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated experience exposes valid current team identity', function () {
    $team = Team::factory()->create(['name' => 'Hiring Team']);
    $user = User::factory()
        ->admin()
        ->teamAdministrator($team)
        ->withCurrentTeam($team)
        ->platformOperator()
        ->create();

    $this->actingAs($user)
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
            ->where('auth.user.role', 'admin'));
});

test('authenticated experience rejects a stale current team selection', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.currentTeam', null)
            ->where('auth.platformOperator', false));
});
