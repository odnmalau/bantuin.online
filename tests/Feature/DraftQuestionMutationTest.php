<?php

use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\QuestionStatus;
use App\Services\DraftQuestionMutation;
use Illuminate\Validation\ValidationException;

test('draft question mutation approves campaign drafts', function () {
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $draft = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Draft]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $mutation = app(DraftQuestionMutation::class);

    $mutation->approveCampaignQuestion($draft);
    $approvedCount = $mutation->approveAllCampaignDrafts($campaign);

    expect($draft->refresh()->status)->toBe(QuestionStatus::Approved)
        ->and($approvedCount)->toBe(0)
        ->and($campaign->questions()->where('status', QuestionStatus::Draft->value)->exists())->toBeFalse();
});

test('approved questions cannot be approved again', function () {
    $question = CampaignQuestion::factory()->create([
        'status' => QuestionStatus::Approved,
    ]);

    expect(fn () => app(DraftQuestionMutation::class)->approveCampaignQuestion($question))
        ->toThrow(ValidationException::class);
});

test('draft question mutation discards only campaign drafts', function () {
    $campaign = Campaign::factory()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $draftOnlySection = CampaignSection::factory()->for($campaign)->create();
    $unrelatedEmptySection = CampaignSection::factory()->for($campaign)->create();
    $draft = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Draft]);
    $draftInDiscardedSection = CampaignQuestion::factory()
        ->for($campaign)
        ->for($draftOnlySection, 'section')
        ->create(['status' => QuestionStatus::Draft]);
    $approved = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $discardedCount = app(DraftQuestionMutation::class)->discardAllCampaignDrafts($campaign);

    expect($discardedCount)->toBe(2);
    $this->assertModelMissing($draft);
    $this->assertModelMissing($draftInDiscardedSection);
    $this->assertModelMissing($draftOnlySection);
    $this->assertModelExists($approved);
    $this->assertModelExists($section);
    $this->assertModelExists($unrelatedEmptySection);
});
