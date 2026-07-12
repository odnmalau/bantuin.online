<?php

use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin campaign show defers campaign and invitations payloads', function () {
    $admin = User::factory()->teamOwner()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create([
        'title' => 'Backend Engineer Screening',
        'role_title' => 'Backend Engineer',
        'status' => CampaignStatus::Draft,
    ]);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
    ]);
    CampaignQuestion::factory()->for($campaign)->for($section, 'section')->create();
    CampaignInvitation::factory()->for($campaign)->create([
        'email' => 'candidate@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/show')
            ->missing('campaign')
            ->missing('invitations')
            ->has('questionTypes')
            ->has('gradingModeOptions')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('campaign.id', $campaign->id)
                ->where('campaign.title', 'Backend Engineer Screening')
                ->has('campaign.sections', 1)
                ->where('campaign.sections.0.title', 'Knowledge Check')
                ->has('campaign.sections.0.questions', 1)
                ->has('invitations', 1)
                ->where('invitations.0.email', 'candidate@example.com'),
            ),
        );
});
