<?php

use App\Models\User;
use App\UserRole;

test('google redirect sends the user to the oauth provider', function () {
    fakeGoogleAuthConfig();
    fakeGoogleRedirect();

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('google callback ignores intended admin url for candidates', function () {
    fakeGoogleAuthConfig();
    fakeGoogleUserAuthentication(
        id: 'google-intended-test',
        email: 'candidate-intended@example.com',
        name: 'Candidate User',
    );

    $response = $this->withSession([
        'url.intended' => 'http://localhost/admin/campaigns',
    ])->get(route('auth.google.callback'));

    $response->assertRedirect(route('candidate.exam', absolute: false));
});

test('google callback logs in a new candidate', function () {
    fakeGoogleAuthConfig();
    fakeGoogleUserAuthentication(
        id: 'google-user-123',
        email: 'candidate@example.com',
        name: 'Candidate User',
        avatar: 'https://lh3.googleusercontent.com/a/candidate-avatar',
    );

    $response = $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'candidate@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-user-123')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/candidate-avatar')
        ->and($user->role)->toBe(UserRole::Candidate);

    $response->assertRedirect(route('candidate.exam', absolute: false));
});

test('google callback links an existing admin without changing role', function () {
    fakeGoogleAuthConfig();

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'google_id' => null,
    ]);

    fakeGoogleUserAuthentication(
        id: 'google-admin-456',
        email: 'admin@example.com',
        name: 'Admin User',
    );

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('admin.rankings.index', absolute: false));

    $admin->refresh();

    expect($admin->google_id)->toBe('google-admin-456')
        ->and($admin->role)->toBe(UserRole::Admin);
});

test('google callback syncs email and avatar from google for returning users', function () {
    fakeGoogleAuthConfig();

    $user = User::factory()->candidate()->create([
        'email' => 'old@example.com',
        'google_id' => 'google-sync-email',
        'avatar' => 'https://lh3.googleusercontent.com/a/old-avatar',
    ]);

    fakeGoogleUserAuthentication(
        id: 'google-sync-email',
        email: 'new@example.com',
        name: 'Updated Name',
        avatar: 'https://lh3.googleusercontent.com/a/new-avatar',
    );

    $this->get(route('auth.google.callback'));

    $user->refresh();

    expect($user->email)->toBe('new@example.com')
        ->and($user->name)->toBe('Updated Name')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/new-avatar');
});

test('google callback keeps existing avatar when google omits one', function () {
    fakeGoogleAuthConfig();

    $user = User::factory()->candidate()->create([
        'email' => 'avatar@example.com',
        'google_id' => 'google-keep-avatar',
        'avatar' => 'https://lh3.googleusercontent.com/a/existing-avatar',
    ]);

    fakeGoogleUserAuthentication(
        id: 'google-keep-avatar',
        email: 'avatar@example.com',
        name: 'Avatar User',
        avatar: null,
    );

    $this->get(route('auth.google.callback'));

    $user->refresh();

    expect($user->avatar)->toBe('https://lh3.googleusercontent.com/a/existing-avatar');
});

test('google callback redirects to login when the user cancels', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});
