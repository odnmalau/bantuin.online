<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Route;

test('google sign-in creates candidate users', function () {
    fakeGoogleAuthConfig();
    fakeGoogleUserAuthentication(
        id: 'google-role-access-1',
        email: 'candidate@example.com',
        name: 'Candidate User',
    );

    $this->get(route('auth.google.callback'));

    $this->assertDatabaseHas('users', [
        'email' => 'candidate@example.com',
        'google_id' => 'google-role-access-1',
        'role' => UserRole::Candidate->value,
    ]);
});

test('admin routes reject candidates', function () {
    $candidate = User::factory()->candidate()->create();

    $this->actingAs($candidate)
        ->get(route('admin.rankings.index'))
        ->assertForbidden();
});

test('candidate routes reject admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('candidate.exam'))
        ->assertForbidden();
});

test('assessment settings admin routes are removed', function () {
    expect(Route::has('admin.assessment-settings.edit'))->toBeFalse()
        ->and(Route::has('admin.assessment-settings.update'))->toBeFalse();
});

test('guests are redirected from role protected routes', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'admin.rankings.index',
    'candidate.exam',
]);

test('admin and candidate can access shared settings routes', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
})->with([
    'admin',
    'candidate',
]);

test('google sign-in never promotes a new account to admin', function () {
    fakeGoogleAuthConfig();
    fakeGoogleUserAuthentication(
        id: 'google-role-access-2',
        email: 'sneaky-admin@example.com',
        name: 'Sneaky Admin',
    );

    $this->get(route('auth.google.callback'));

    $this->assertDatabaseHas('users', [
        'email' => 'sneaky-admin@example.com',
        'role' => UserRole::Candidate->value,
    ]);
});
