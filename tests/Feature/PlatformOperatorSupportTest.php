<?php

use App\ExamSessionStatus;
use App\Models\Campaign;
use App\Models\ExamSession;
use App\Models\OwnershipTransfer;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\OwnershipTransferStatus;
use App\Services\OwnershipTransferService;
use App\Services\TeamInvitationService;
use App\Services\TeamLifecycleService;
use App\TeamInvitationStatus;
use App\TeamMembershipRole;
use App\TeamStatus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('only platform operators enter the separate support area', function () {
    $team = Team::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('support.teams.index'))->assertForbidden();

    $operator = User::factory()->platformOperator()->create();
    $this->actingAs($operator)->get(route('support.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('support/teams/index')->has('teams.data', 1));

    expect($operator->activeTeamMemberships()->count())->toBe(0);
});

test('support detail exposes only membership invitation metadata and aggregate hiring counts', function () {
    $team = Team::factory()->create(['name' => 'Support target']);
    $operator = User::factory()->platformOperator()->create();

    $response = $this->actingAs($operator)->get(route('support.teams.show', $team));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('support/teams/show')
        ->where('team.name', 'Support target')
        ->hasAll(['memberships', 'invitations', 'counts.campaigns', 'counts.campaign_invitations', 'counts.assessments', 'counts.exam_sessions'])
        ->missingAll(['campaigns', 'candidates', 'answers', 'scores', 'rankings', 'assessment_decisions', 'hiring_actions']));

    foreach (['resume_path', 'resume_text', 'answers_payload', 'ranking_payload', 'token_hash'] as $forbidden) {
        $response->assertDontSee($forbidden);
    }
});

test('membership repair requires a reason and recipient consent while preserving conflicts', function () {
    Notification::fake();
    $team = Team::factory()->create();
    $operator = User::factory()->platformOperator()->create();
    $recipient = User::factory()->create(['email' => 'repair@example.com']);

    $this->actingAs($operator)->post(route('support.teams.membership-repairs.store', $team), [
        'email' => $recipient->email,
        'role' => TeamMembershipRole::Collaborator->value,
        'reason' => '   ',
    ])->assertSessionHasErrors('reason');

    $this->actingAs($operator)->post(route('support.teams.membership-repairs.store', $team), [
        'email' => $recipient->email,
        'role' => TeamMembershipRole::Collaborator->value,
        'reason' => 'Restore access after identity verification',
    ])->assertSessionHasNoErrors();

    $invitation = TeamInvitation::query()->sole();
    expect($recipient->activeTeamMemberships()->whereBelongsTo($team)->exists())->toBeFalse()
        ->and($invitation->actor_context)->toBe('platform_operator');

    app(TeamInvitationService::class)->accept($invitation, $recipient);

    expect($recipient->activeTeamMemberships()->whereBelongsTo($team)->sole()->role)->toBe(TeamMembershipRole::Collaborator)
        ->and($team->activities()->where('action', 'team_invitation_issued')->sole()->reason)->toBe('Restore access after identity verification');
});

test('operator ownership transfer still requires recipient acceptance and preserves one owner', function () {
    Notification::fake();
    $team = Team::factory()->create();
    $originalOwner = $team->ownerMembership->user;
    $recipient = User::factory()->teamAdministrator($team)->create();
    $operator = User::factory()->platformOperator()->create();
    $membership = $recipient->activeTeamMemberships()->whereBelongsTo($team)->sole();

    $this->actingAs($operator)->post(route('support.teams.ownership-transfers.store', $team), [
        'membership_id' => $membership->id,
        'reason' => 'Owner unavailable after account incident',
    ])->assertSessionHasNoErrors();

    $transfer = OwnershipTransfer::query()->sole();
    expect($transfer->status)->toBe(OwnershipTransferStatus::Pending)
        ->and($team->ownerMembership->user_id)->toBe($originalOwner->id)
        ->and($operator->activeTeamMemberships()->count())->toBe(0);

    app(OwnershipTransferService::class)->accept($transfer, $recipient);
    expect($team->ownerMembership()->sole()->user_id)->toBe($recipient->id)
        ->and($team->activeMemberships()->where('role', TeamMembershipRole::Owner)->count())->toBe(1);
});

test('operator lifecycle actions require reasons and retain the lifecycle invariants', function () {
    $team = Team::factory()->create();
    $operator = User::factory()->platformOperator()->create();

    $this->actingAs($operator)->post(route('support.teams.deactivate', $team), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($operator)->post(route('support.teams.deactivate', $team), ['reason' => 'Fraud response freeze'])->assertSessionHasNoErrors();

    expect($team->fresh()->status)->toBe(TeamStatus::Deactivated);
    $activity = $team->activities()->where('action', 'team_deactivated')->sole();
    expect($activity->actor_id)->toBe($operator->id)
        ->and($activity->actor_context)->toBe('platform_operator')
        ->and($activity->reason)->toBe('Fraud response freeze')
        ->and($activity->before_state)->toBe(['status' => 'active'])
        ->and($activity->after_state)->toBe(['status' => 'deactivated']);

    $this->actingAs($operator)->post(route('support.teams.reactivate', $team), ['reason' => 'Incident resolved'])->assertSessionHasNoErrors();
    expect($team->fresh()->status)->toBe(TeamStatus::Active);
});

test('an in progress exam blocks operator deactivation', function () {
    $team = Team::factory()->create();
    $operator = User::factory()->platformOperator()->create();
    $campaign = Campaign::factory()->for($team)->active()->create();
    $examSession = ExamSession::factory()->for($campaign)->create();

    $this->actingAs($operator)->post(route('support.teams.deactivate', $team), [
        'reason' => 'Lifecycle incident',
    ])->assertSessionHasErrors('team');

    expect($team->fresh()->status)->toBe(TeamStatus::Active)
        ->and($examSession->fresh()->status)->toBe(ExamSessionStatus::InProgress)
        ->and($team->activities()->where('action', 'team_deactivated')->exists())->toBeFalse();
});

test('support reasons are enforced at the mutation service boundary', function () {
    $team = Team::factory()->create();
    $operator = User::factory()->platformOperator()->create();

    expect(fn () => app(TeamLifecycleService::class)->deactivateByOperator($team, $operator, '   '))
        ->toThrow(ValidationException::class);
});

test('revoked operator authority invalidates pending repair acceptance', function () {
    Notification::fake();
    $team = Team::factory()->create();
    $operator = User::factory()->platformOperator()->create();
    $recipient = User::factory()->create(['email' => 'recipient@example.com']);
    $invitation = app(TeamInvitationService::class)->issueByOperator($team, $operator, $recipient->email, TeamMembershipRole::Collaborator, 'Access repair');
    $operator->platformOperatorAuthorities()->active()->update(['revoked_at' => now()]);

    expect(fn () => app(TeamInvitationService::class)->accept($invitation, $recipient))
        ->toThrow(ValidationException::class);

    expect($invitation->fresh()->status)->toBe(TeamInvitationStatus::Pending);
});
