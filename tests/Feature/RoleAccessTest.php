<?php

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('google sign-in creates an identity without contextual authority', function () {
    fakeGoogleAuthConfig();
    fakeGoogleUserAuthentication(
        id: 'google-contextual-access-1',
        email: 'person@example.com',
        name: 'Person',
    );

    $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'person@example.com')->sole();

    expect($user->activeTeamMemberships()->exists())->toBeFalse()
        ->and($user->campaignInvitations()->exists())->toBeFalse()
        ->and($user->isPlatformOperator())->toBeFalse();
});

test('team administration requires membership in the current team', function () {
    $team = Team::factory()->create();
    $member = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();
    $outsider = User::factory()->create();

    $this->actingAs($member)->get(route('admin.rankings.index'))->assertOk();
    $this->actingAs($outsider)->get(route('admin.rankings.index'))->assertForbidden();
});

test('candidate work requires campaign participation rather than team context', function () {
    $team = Team::factory()->create();
    $participant = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();
    $campaign = Campaign::factory()->active()->create();
    CampaignInvitation::factory()->for($campaign)->accepted($participant)->create();

    expect($participant->campaignInvitations()->acceptedForUser($participant)->exists())->toBeTrue();

    $this->actingAs($participant)
        ->get(route('candidate.exam'))
        ->assertOk();
});

test('platform operator access requires active operator authority', function () {
    $this->withoutVite();

    $operator = User::factory()->platformOperator()->create();
    $user = User::factory()->create();

    $this->actingAs($operator)->get(route('support.teams.index'))->assertOk();
    $this->actingAs($user)->get(route('support.teams.index'))->assertForbidden();
});

test('assessment settings admin routes are removed', function () {
    expect(Route::has('admin.assessment-settings.edit'))->toBeFalse()
        ->and(Route::has('admin.assessment-settings.update'))->toBeFalse();
});

test('guests are redirected from contextual routes', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'admin.rankings.index',
    'candidate.exam',
]);

test('authenticated identities can access shared settings routes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});
