<?php

use App\Models\User;
use App\TeamMembershipRole;
use Inertia\Testing\AssertableInertia as Assert;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
        );
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('guests can log in as a demo admin', function () {
    $response = $this->post(route('auth.demo.admin'));

    $user = User::query()->where('email', 'demo-admin@bantuin.online')->firstOrFail();

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $membership = $user->activeTeamMemberships()
        ->where('team_id', $user->current_team_id)
        ->firstOrFail();

    expect($user->current_team_id)->not->toBeNull()
        ->and($membership->role)->toBe(TeamMembershipRole::Owner);
});

test('demo login reuses an existing candidate account', function () {
    $user = User::factory()->create([
        'name' => 'Existing Demo Candidate',
        'email' => 'demo-candidate@bantuin.online',
    ]);

    $response = $this->post(route('auth.demo.candidate'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    expect(User::query()->where('email', $user->email)->count())->toBe(1);
});
