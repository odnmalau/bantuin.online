<?php

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\ExamSession;
use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\OwnershipTransferService;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile name can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

test('profile email cannot be changed through settings', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'changed@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('original@example.com');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'confirmation' => 'DELETE',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::query()->find($user->id))->toBeNull();
    $this->assertSoftDeleted($user);
});

test('a team owner cannot close their account until ownership transfer completes', function () {
    $team = Team::factory()->create();
    $owner = $team->ownerMembership->user;
    $owner->selectCurrentTeam($team);

    $this->actingAs($owner)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertSessionHasErrors('account')
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticatedAs($owner);
    expect($owner->fresh())->not->toBeNull();
});

test('owning a non current team also prevents account closure', function () {
    $ownedTeam = Team::factory()->create();
    $owner = $ownedTeam->ownerMembership->user;
    $currentTeam = Team::factory()->create();
    TeamMembership::factory()->for($currentTeam)->for($owner)->collaborator()->create();
    $owner->selectCurrentTeam($currentTeam);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertSessionHasErrors('account');

    expect(User::query()->find($owner->id))->not->toBeNull();
});

test('a previous owner can close their account after ownership transfer completes', function () {
    $team = Team::factory()->create();
    $previousOwner = $team->ownerMembership->user;
    $recipient = User::factory()->teamAdministrator($team)->create();
    $recipientMembership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();
    $previousOwner->selectCurrentTeam($team);
    $transfer = OwnershipTransfer::factory()->for($team)->create([
        'owner_membership_id' => $team->ownerMembership->id,
        'recipient_membership_id' => $recipientMembership->id,
    ]);
    app(OwnershipTransferService::class)->accept($transfer, $recipient);

    $this->actingAs($previousOwner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertSessionHasNoErrors();

    $this->assertGuest();
    $this->assertSoftDeleted($previousOwner);
    expect($team->ownerMembership()->sole()->user_id)->toBe($recipient->id);
});

test('closing a non owner account ends access and preserves durable hiring history', function () {
    $team = Team::factory()->create();
    $user = User::factory()
        ->teamCollaborator($team)
        ->withCurrentTeam($team)
        ->platformOperator()
        ->create();
    $candidateTeam = Team::factory()->create();
    $campaign = Campaign::factory()->for($candidateTeam)->active()->create();
    $invitation = CampaignInvitation::factory()->for($campaign)->accepted($user)->create();
    $assessment = Assessment::factory()->for($campaign)->for($user)->create();
    $examSession = ExamSession::factory()->for($campaign)->for($user)->create(['assessment_id' => $assessment->id]);
    $membership = $user->activeTeamMemberships()->whereBelongsTo($team)->sole();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    expect($membership->fresh()->ended_at)->not->toBeNull()
        ->and($membership->team->activities()->where('action', 'account_closed_membership_ended')->exists())->toBeTrue()
        ->and($user->platformOperatorAuthorities()->active()->exists())->toBeFalse()
        ->and(CampaignInvitation::query()->find($invitation->id))->not->toBeNull()
        ->and(Assessment::query()->find($assessment->id))->not->toBeNull()
        ->and(ExamSession::query()->find($examSession->id))->not->toBeNull();
});

test('account deletion requires typing DELETE', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'confirmation' => 'delete',
        ]);

    $response
        ->assertSessionHasErrors('confirmation')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
