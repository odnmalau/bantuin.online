<?php

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create([
        'google_id' => 'google-user-123',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('overview', null)
            ->where('personalLanding', true)
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ]),
        );
});

test('candidate-only users are redirected from the dashboard to their assessments', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();

    CampaignInvitation::factory()
        ->for($campaign)
        ->accepted($candidate)
        ->create();

    $this->actingAs($candidate)
        ->get(route('dashboard'))
        ->assertRedirect(route('candidate.exam'));
});
