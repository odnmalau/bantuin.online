<?php

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\User;
use App\QuestionStatus;
use App\Services\CandidateExamPage;
use App\Services\ExamSessionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

test('candidate exam page returns no campaign state', function () {
    $candidate = User::factory()->create();

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

test('candidate exam page returns campaign picker state with progress badges', function () {
    $candidate = User::factory()->create();
    $notStarted = Campaign::factory()->active()->create([
        'title' => 'Not Started Campaign',
        'role_title' => 'Analyst',
    ]);
    $inProgress = Campaign::factory()->active()->create([
        'title' => 'In Progress Campaign',
        'role_title' => 'Engineer',
    ]);
    $submitted = Campaign::factory()->active()->create([
        'title' => 'Submitted Campaign',
        'role_title' => 'Designer',
    ]);

    foreach ([$notStarted, $inProgress, $submitted] as $campaign) {
        $section = CampaignSection::factory()->for($campaign)->create();
        CampaignQuestion::factory()
            ->for($campaign)
            ->for($section, 'section')
            ->create(['status' => QuestionStatus::Approved]);
    }

    assignCandidateToCampaignExam($candidate, $inProgress);
    app(ExamSessionService::class)->startSession($candidate, $inProgress);
    Assessment::factory()->for($candidate)->for($submitted)->create();
    $notStartedTeamName = $notStarted->team->name;
    $teamQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$teamQueries): void {
        if (preg_match('/^select "id", "name" from "teams"/i', $query->sql) === 1) {
            $teamQueries[] = $query->sql;
        }
    });

    $page = app(CandidateExamPage::class)->picker(
        $candidate,
        collect([$notStarted->fresh(), $inProgress->fresh(), $submitted->fresh()]),
    );

    expect($page['state'])->toBe('campaign_picker')
        ->and($page['campaign'])->toBeNull()
        ->and($page['campaigns'])->toHaveCount(3)
        ->and($page['campaigns'][0])->toMatchArray([
            'id' => $notStarted->id,
            'title' => 'Not Started Campaign',
            'role_title' => 'Analyst',
            'team' => ['name' => $notStartedTeamName],
            'progress' => 'not_started',
        ])
        ->and($page['campaigns'][1]['progress'])->toBe('in_progress')
        ->and($page['campaigns'][2]['progress'])->toBe('submitted')
        ->and($teamQueries)->toHaveCount(1);
});

test('candidate exam page returns ready to start state', function () {
    $candidate = User::factory()->create();
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
        ->and($page['secure_exam'])->toBe([
            'require_fullscreen' => true,
            'block_copy_paste' => true,
        ])
        ->and(array_key_exists('examSession', $page))->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns active section state', function () {
    $candidate = User::factory()->create();
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
    assignCandidateToCampaignExam($candidate, $campaign);
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
        ->and($page['examSession']['secure_exam'])->toBe([
            'require_fullscreen' => true,
            'block_copy_paste' => true,
        ])
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns ready to finalize state', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    $section = CampaignSection::factory()->for($campaign)->create();
    $question = CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create([
            'status' => QuestionStatus::Approved,
        ]);
    assignCandidateToCampaignExam($candidate, $campaign);
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
        ->and($page['examSession']['secure_exam'])->toBe([
            'require_fullscreen' => true,
            'block_copy_paste' => true,
        ])
        ->and(array_key_exists('currentSection', $page))->toBeFalse()
        ->and(array_key_exists('questions', $page))->toBeFalse()
        ->and(array_key_exists('assessment', $page))->toBeFalse();
});

test('candidate exam page returns submitted state', function () {
    $candidate = User::factory()->create();
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
