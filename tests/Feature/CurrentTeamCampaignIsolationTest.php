<?php

use App\CampaignStatus;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\Team;
use App\Models\User;
use App\QuestionStatus;
use App\QuestionType;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('each authorized Team membership can manage campaigns', function (string $role) {
    $team = Team::factory()->create();
    $user = match ($role) {
        'owner' => $team->ownerMembership->user,
        'administrator' => User::factory()->teamAdministrator($team)->create(),
        'collaborator' => User::factory()->teamCollaborator($team)->create(),
    };
    $user->selectCurrentTeam($team);

    $this->actingAs($user)
        ->post(route('admin.campaigns.store'), [
            'title' => 'Contextual Campaign',
            'role_title' => 'Backend Engineer',
            'threshold_score' => 75,
        ])
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->where('title', 'Contextual Campaign')->sole();

    $this->actingAs($user)->get(route('admin.campaigns.index'))->assertOk();
    $this->actingAs($user)->get(route('admin.campaigns.show', $campaign))->assertOk();
    $this->actingAs($user)->get(route('admin.campaigns.edit', $campaign))->assertOk();

    $this->actingAs($user)
        ->patch(route('admin.campaigns.update', $campaign), [
            'title' => 'Updated Contextual Campaign',
            'role_title' => 'Senior Backend Engineer',
            'threshold_score' => 80,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->patch(route('admin.campaigns.ranking.update', $campaign), [
            'ranking_weights' => ['resume_score' => 40, 'assessment_score' => 60],
        ])
        ->assertSessionHasNoErrors();

    $temporarySection = CampaignSection::factory()->for($campaign)->create();
    $this->actingAs($user)
        ->delete(route('admin.campaigns.sections.destroy', [$campaign, $temporarySection]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('admin.campaigns.sections.store', $campaign), [
            'title' => 'Technical Reasoning',
            'description' => 'Evaluate practical engineering decisions.',
            'duration_minutes' => 30,
            'weight' => 100,
        ])
        ->assertSessionHasNoErrors();

    $section = $campaign->sections()->sole();
    $this->actingAs($user)
        ->post(route('admin.campaigns.questions.store', $campaign), [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::LongText->value,
            'prompt' => 'Explain how you would diagnose a slow API.',
            'expected_rubric' => 'Mentions evidence, queries, and verification.',
            'points' => 10,
            'difficulty' => 'medium',
            'ai_generated' => true,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors();
    $question = $campaign->questions()->sole();
    $question->update(['status' => QuestionStatus::Draft]);

    $this->actingAs($user)
        ->post(route('admin.campaigns.questions.approve', [$campaign, $question]))
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('admin.campaigns.publish', $campaign))->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('admin.campaigns.draft', $campaign))->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('admin.campaigns.archive', $campaign))->assertSessionHasNoErrors();

    expect($campaign->team_id)->toBe($team->id)
        ->and($question->refresh()->status)->toBe(QuestionStatus::Approved);

    $this->actingAs($user)->delete(route('admin.campaigns.destroy', $campaign))->assertRedirect(route('admin.campaigns.index'));

    expect(Campaign::query()->whereKey($campaign->id)->exists())->toBeFalse();
})->with(['owner', 'administrator', 'collaborator']);

test('campaign index and aggregate counts include only the current team', function () {
    $currentTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->teamCollaborator($currentTeam)->withCurrentTeam($currentTeam)->create();
    $visible = Campaign::factory()->for($currentTeam)->create(['title' => 'Visible Campaign']);
    Campaign::factory()->for($otherTeam)->create(['title' => 'Secret Campaign']);
    CampaignSection::factory()->for($visible)->create();

    $this->actingAs($user)
        ->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('campaigns.total', 1)
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $visible->id)
                ->where('campaigns.data.0.sections_count', 1)));
});

test('campaign and nested authoring endpoints reject cross team identifiers', function (string $routeName, string $method) {
    $currentTeam = Team::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $user = User::factory()->teamCollaborator($currentTeam)->withCurrentTeam($currentTeam)->create();
    $section = CampaignSection::factory()->for($otherCampaign)->create();
    $question = CampaignQuestion::factory()->for($otherCampaign)->for($section, 'section')->create();

    $parameters = match ($routeName) {
        'admin.campaigns.sections.update',
        'admin.campaigns.sections.destroy' => [$otherCampaign, $section],
        'admin.campaigns.questions.approve' => [$otherCampaign, $question],
        default => [$otherCampaign],
    };

    $this->actingAs($user)
        ->call($method, route($routeName, $parameters))
        ->assertNotFound();
})->with([
    ['admin.campaigns.show', 'GET'],
    ['admin.campaigns.edit', 'GET'],
    ['admin.campaigns.update', 'PATCH'],
    ['admin.campaigns.publish', 'POST'],
    ['admin.campaigns.archive', 'POST'],
    ['admin.campaigns.ranking.update', 'PATCH'],
    ['admin.campaigns.sections.update', 'PATCH'],
    ['admin.campaigns.sections.destroy', 'DELETE'],
    ['admin.campaigns.questions.approve', 'POST'],
    ['admin.campaigns.questions.discard-all', 'DELETE'],
]);

test('deactivated current teams remain readable but reject campaign mutations', function () {
    $team = Team::factory()->deactivated()->create();
    $campaign = Campaign::factory()->for($team)->create();
    $user = User::factory()->teamCollaborator($team)->withCurrentTeam($team)->create();

    $this->actingAs($user)->get(route('admin.campaigns.show', $campaign))->assertOk();
    $this->actingAs($user)->post(route('admin.campaigns.archive', $campaign))->assertForbidden();

    expect($campaign->fresh()->status)->not->toBe(CampaignStatus::Archived);
});

test('deferred campaign data rechecks current team on every request', function () {
    $firstTeam = Team::factory()->create();
    $secondTeam = Team::factory()->create();
    $campaign = Campaign::factory()->for($firstTeam)->create();
    CampaignInvitation::factory()->for($campaign)->create(['email' => 'private@example.com']);
    $user = User::factory()->teamCollaborator($firstTeam)->teamCollaborator($secondTeam)->withCurrentTeam($firstTeam)->create();

    $this->actingAs($user)->get(route('admin.campaigns.show', $campaign))->assertOk();

    $user->selectCurrentTeam($secondTeam);

    $this->actingAs($user)
        ->get(route('admin.campaigns.show', $campaign), [
            'X-Inertia-Partial-Component' => 'admin/campaigns/show',
            'X-Inertia-Partial-Data' => 'campaign,invitations',
        ])
        ->assertNotFound();
});

test('a user without a current team cannot access campaign management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.campaigns.index'))->assertForbidden();
});

test('assessment review rejects a cross team identifier', function () {
    $currentTeam = Team::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $assessment = Assessment::factory()->for($otherCampaign)->create();
    $user = User::factory()->teamCollaborator($currentTeam)->withCurrentTeam($currentTeam)->create();

    $this->actingAs($user)
        ->get(route('admin.assessments.show', $assessment))
        ->assertNotFound();
});

test('assessment decisions and recovery reject cross team identifiers', function (string $routeName) {
    $currentTeam = Team::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $assessment = Assessment::factory()->for($otherCampaign)->create();
    $original = $assessment->fresh()->toArray();
    $user = User::factory()->teamCollaborator($currentTeam)->withCurrentTeam($currentTeam)->create();

    $this->actingAs($user)
        ->post(route($routeName, $assessment))
        ->assertNotFound();

    expect($assessment->fresh()->toArray())->toBe($original);
})->with([
    'retry evaluation' => 'admin.assessments.retry-evaluation',
    'retry email' => 'admin.assessments.retry-email',
    'promote' => 'admin.assessments.promote',
    'override score' => 'admin.assessments.override-score',
    'approve' => 'admin.assessments.approve',
    'reject' => 'admin.assessments.reject',
]);
