<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard shares configured docs url with the frontend', function () {
    config(['app.docs_url' => 'https://docs.bantuin.online']);

    $user = User::factory()->create([
        'google_id' => 'google-docs-url-123',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('docsUrl', 'https://docs.bantuin.online'),
        );
});

test('dashboard shares null docs url when docs url is empty', function () {
    config(['app.docs_url' => '']);

    $user = User::factory()->create([
        'google_id' => 'google-docs-url-empty',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('docsUrl', null),
        );
});
