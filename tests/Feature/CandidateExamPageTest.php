<?php

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionStatus;
use App\Services\CandidateExamPage;
use App\Services\ExamSessionService;

test('candidate exam page returns no campaign state', function () {
    $candidate = User::factory()->candidate()->create();

    $page = app(CandidateExamPage::class)->for($candidate, null);

    expect($page)
        ->toMatchArray([
            'state' => 'no_campaign',
            'campaign' => null,
        ])
        ->and(array_key_exists('sections', $page))->toBeFalse()
        ->and(array_key_exists('examSession', $page))->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns ready to start state', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create([
        'title' => 'Backend Engineer Campaign',
        'role_title' => 'Backend Engineer',
    ]);
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
        'duration_minutes' => 30,
    ]);
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);

    $page = app(CandidateExamPage::class)->for($candidate, $campaign);

    expect($page['state'])->toBe('ready_to_start')
        ->and($page['campaign']['id'])->toBe($campaign->id)
        ->and($page['campaign']['title'])->toBe('Backend Engineer Campaign')
        ->and($page['campaign']['role_title'])->toBe('Backend Engineer')
        ->and($page['sections'])->toHaveCount(1)
        ->and($page['sections'][0]['id'])->toBe($section->id)
        ->and($page['sections'][0]['title'])->toBe('Knowledge Check')
        ->and($page['sections'][0]['duration_minutes'])->toBe(30)
        ->and($page['sections'][0]['question_count'])->toBe(1)
        ->and(array_key_exists('examSession', $page))->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns active section state', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create([
        'title' => 'Knowledge Check',
    ]);
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'prompt' => 'Explain dependency injection.',
            'status' => QuestionStatus::Approved,
        ]);
    $session = app(ExamSessionService::class)->startSession($candidate, $campaign);

    $page = app(CandidateExamPage::class)->for($candidate, $campaign);

    expect($page['state'])->toBe('active_section')
        ->and($page['campaign']['id'])->toBe($campaign->id)
        ->and($page['currentSection']['id'])->toBe($section->id)
        ->and($page['currentSection']['title'])->toBe('Knowledge Check')
        ->and($page['currentSection']['question_count'])->toBe(1)
        ->and($page['questions'])->toHaveCount(1)
        ->and($page['questions'][0]['id'])->toBe($question->id)
        ->and($page['questions'][0]['content'])->toBe('Explain dependency injection.')
        ->and($page['examSession']['id'])->toBe($session->id)
        ->and($page['examSession']['current_section_id'])->toBe($section->id)
        ->and($page['examSession']['ready_to_finalize'])->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns ready to finalize state', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);
    $sessions = app(ExamSessionService::class);
    $session = $sessions->startSession($candidate, $campaign);

    $session = $sessions->saveCurrentSectionAnswers($session, $campaign, [
        $question->id => 'Answered.',
    ]);
    $sessions->advanceSection($session, $campaign);

    $page = app(CandidateExamPage::class)->for($candidate, $campaign);

    expect($page['state'])->toBe('ready_to_finalize')
        ->and($page['campaign']['id'])->toBe($campaign->id)
        ->and($page['sections'])->toHaveCount(1)
        ->and($page['sections'][0]['id'])->toBe($section->id)
        ->and($page['sections'][0]['question_count'])->toBe(1)
        ->and($page['examSession']['id'])->toBe($session->id)
        ->and($page['examSession']['current_section_id'])->toBeNull()
        ->and($page['examSession']['ready_to_finalize'])->toBeTrue()
        ->and(array_key_exists('currentSection', $page))->toBeFalse()
        ->and(array_key_exists('questions', $page))->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns submitted state', function () {
    $candidate = User::factory()->candidate()->create();
    $campaign = Campaign::factory()->active()->create();
    $assessment = Assessment::factory()
        ->for($candidate)
        ->for($campaign)
        ->create();

    $page = app(CandidateExamPage::class)->for($candidate, $campaign);

    expect($page['state'])->toBe('submitted')
        ->and($page['campaign']['id'])->toBe($campaign->id)
        ->and($page['assessment']['id'])->toBe($assessment->id)
        ->and($page['assessment']['status'])->toBe($assessment->status->value)
        ->and(array_key_exists('sections', $page))->toBeFalse()
        ->and(array_key_exists('examSession', $page))->toBeFalse();
});
