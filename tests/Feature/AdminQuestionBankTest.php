<?php

use App\Models\BankQuestion;
use App\Models\Campaign;
use App\Models\CampaignSection;
use App\Models\QuestionBank;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionType;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('admin can view question libraries', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create([
        'title' => 'Laravel Backend - Mid Level',
        'skill_area' => 'Laravel',
    ]);
    BankQuestion::factory()->for($questionBank)->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.question-banks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/question-banks/index')
            ->has('questionBanks', 1)
            ->where('questionBanks.0.id', $questionBank->id)
            ->where('questionBanks.0.title', 'Laravel Backend - Mid Level')
            ->where('questionBanks.0.questions_count', 2),
        );
});

test('admin can create and update a question library', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.question-banks.store'), [
            'title' => 'PostgreSQL Performance',
            'description' => 'Indexes, query plans, and locking.',
            'skill_area' => 'PostgreSQL',
            'difficulty' => 'hard',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $questionBank = QuestionBank::query()->where('title', 'PostgreSQL Performance')->sole();

    $response->assertRedirect(route('admin.question-banks.show', $questionBank));

    expect($questionBank)
        ->created_by->toBe($admin->id)
        ->description->toContain('Indexes')
        ->difficulty->toBe('hard')
        ->is_active->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.question-banks.update', $questionBank), [
            'title' => 'PostgreSQL Reliability',
            'description' => 'Transactions and lock monitoring.',
            'skill_area' => 'PostgreSQL',
            'difficulty' => 'medium',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.question-banks.show', $questionBank));

    expect($questionBank->refresh())
        ->title->toBe('PostgreSQL Reliability')
        ->difficulty->toBe('medium')
        ->is_active->toBeFalse();
});

test('admin can create and update reusable bank questions', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.question-banks.questions.store', $questionBank), [
            'type' => QuestionType::LongText->value,
            'prompt' => 'Explain how you would debug queue worker failures.',
            'expected_rubric' => 'Mentions retries, failed jobs, logs, idempotency, and monitoring.',
            'points' => 25,
            'difficulty' => 'medium',
            'skill_tags_text' => "Laravel\nQueues",
            'ai_generated' => false,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.question-banks.show', $questionBank));

    $question = $questionBank->questions()->sole();

    expect($question)
        ->type->toBe(QuestionType::LongText)
        ->grading_mode->toBe(QuestionGradingMode::Ai)
        ->skill_tags->toBe(['Laravel', 'Queues'])
        ->expected_rubric->toContain('idempotency');

    $this->actingAs($admin)
        ->patch(route('admin.question-banks.questions.update', [$questionBank, $question]), [
            'type' => QuestionType::ShortText->value,
            'prompt' => 'Name two signals for queue worker failures.',
            'expected_rubric' => 'Mentions failed jobs and logs.',
            'points' => 10,
            'difficulty' => 'easy',
            'skill_tags_text' => 'Queues',
            'ai_generated' => false,
            'sort_order' => 20,
        ])
        ->assertSessionHasNoErrors();

    expect($question->refresh())
        ->type->toBe(QuestionType::ShortText)
        ->grading_mode->toBe(QuestionGradingMode::Ai)
        ->prompt->toBe('Name two signals for queue worker failures.')
        ->points->toBe(10)
        ->sort_order->toBe(20);
});

test('admin can create reusable multiple choice questions', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.question-banks.questions.store', $questionBank), [
            'type' => QuestionType::MultipleChoice->value,
            'prompt' => 'Which command runs failed queue jobs again?',
            'options_text' => "queue:work\nqueue:retry\nqueue:listen\nqueue:clear",
            'correct_answer_text' => 'queue:retry',
            'points' => 10,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors();

    $question = $questionBank->questions()->sole();

    expect($question)
        ->type->toBe(QuestionType::MultipleChoice)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->options->toBe(['queue:work', 'queue:retry', 'queue:listen', 'queue:clear'])
        ->correct_answer->toBe(['queue:retry'])
        ->expected_rubric->toBeNull();
});

test('admin can create reusable yes no questions', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.question-banks.questions.store', $questionBank), [
            'type' => QuestionType::YesNo->value,
            'prompt' => 'Should queue workers be idempotent?',
            'correct_answer_text' => 'Yes',
            'points' => 5,
            'difficulty' => 'easy',
            'ai_generated' => false,
            'sort_order' => 5,
        ])
        ->assertSessionHasNoErrors();

    $question = $questionBank->questions()->sole();

    expect($question)
        ->type->toBe(QuestionType::YesNo)
        ->grading_mode->toBe(QuestionGradingMode::Deterministic)
        ->correct_answer->toBe(['Yes']);
});

test('bank question validation enforces grading inputs', function (array $payload, string $errorKey) {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->from(route('admin.question-banks.show', $questionBank))
        ->post(route('admin.question-banks.questions.store', $questionBank), [
            'prompt' => 'Validation probe',
            'points' => 10,
            'difficulty' => 'medium',
            'ai_generated' => false,
            'sort_order' => 10,
            ...$payload,
        ])
        ->assertSessionHasErrors($errorKey)
        ->assertRedirect(route('admin.question-banks.show', $questionBank));

    expect($questionBank->questions()->exists())->toBeFalse();
})->with([
    'mcq needs answer key' => [
        [
            'type' => QuestionType::MultipleChoice->value,
            'options_text' => "A\nB",
        ],
        'correct_answer_text',
    ],
    'text needs rubric' => [
        [
            'type' => QuestionType::LongText->value,
        ],
        'expected_rubric',
    ],
    'grading mode must be supported' => [
        [
            'type' => QuestionType::LongText->value,
            'grading_mode' => 'spreadsheet',
            'expected_rubric' => 'Uses supported grading mode.',
        ],
        'grading_mode',
    ],
]);

test('admin can import bank question to campaign as a copy', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create([
        'title' => 'Laravel Backend',
    ]);
    $bankQuestion = BankQuestion::factory()->for($questionBank)->create([
        'type' => QuestionType::LongText,
        'prompt' => 'Explain queue retry design.',
        'expected_rubric' => 'Looks for backoff, idempotency, and dead-letter handling.',
        'points' => 20,
        'difficulty' => 'hard',
        'skill_tags' => ['Queues', 'Reliability'],
    ]);
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.import', $campaign), [
            'bank_question_id' => $bankQuestion->id,
            'campaign_section_id' => $section->id,
            'is_required' => true,
            'sort_order' => 30,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    $campaignQuestion = $campaign->questions()->sole();

    expect($campaignQuestion)
        ->source_bank_question_id->toBe($bankQuestion->id)
        ->campaign_section_id->toBe($section->id)
        ->grading_mode->toBe(QuestionGradingMode::Ai)
        ->prompt->toBe('Explain queue retry design.')
        ->expected_rubric->toContain('dead-letter')
        ->points->toBe(20)
        ->skill_tags->toBe(['Queues', 'Reliability']);

    $bankQuestion->update([
        'prompt' => 'Changed in library only.',
        'points' => 99,
    ]);

    expect($campaignQuestion->refresh())
        ->prompt->toBe('Explain queue retry design.')
        ->points->toBe(20);
});

test('admin can edit campaign question snapshot without changing library source', function () {
    $admin = User::factory()->admin()->create();
    $questionBank = QuestionBank::factory()->for($admin, 'creator')->create();
    $bankQuestion = BankQuestion::factory()->for($questionBank)->create([
        'type' => QuestionType::LongText,
        'prompt' => 'Explain queue retry design.',
        'expected_rubric' => 'Looks for backoff and idempotency.',
        'points' => 20,
        'difficulty' => 'hard',
        'skill_tags' => ['Queues'],
    ]);
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $section = CampaignSection::factory()->for($campaign)->create();

    $this->actingAs($admin)
        ->post(route('admin.campaigns.questions.import', $campaign), [
            'bank_question_id' => $bankQuestion->id,
            'campaign_section_id' => $section->id,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasNoErrors();

    $campaignQuestion = $campaign->questions()->sole();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->patch(route('admin.campaigns.questions.update', [$campaign, $campaignQuestion]), [
            'campaign_section_id' => $section->id,
            'type' => QuestionType::ShortText->value,
            'grading_mode' => QuestionGradingMode::Manual->value,
            'prompt' => 'Name two queue retry safeguards.',
            'expected_rubric' => 'Mentions backoff and idempotency.',
            'points' => 15,
            'difficulty' => 'medium',
            'skill_tags_text' => "Queues\nReliability",
            'ai_generated' => true,
            'is_required' => false,
            'sort_order' => 20,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaignQuestion->refresh())
        ->source_bank_question_id->toBe($bankQuestion->id)
        ->type->toBe(QuestionType::ShortText)
        ->grading_mode->toBe(QuestionGradingMode::Manual)
        ->prompt->toBe('Name two queue retry safeguards.')
        ->points->toBe(15)
        ->difficulty->toBe('medium')
        ->skill_tags->toBe(['Queues', 'Reliability'])
        ->is_required->toBeFalse()
        ->sort_order->toBe(20)
        ->and($bankQuestion->refresh())
        ->prompt->toBe('Explain queue retry design.')
        ->points->toBe(20)
        ->difficulty->toBe('hard')
        ->skill_tags->toBe(['Queues']);
});

test('import target section must belong to campaign', function () {
    $admin = User::factory()->admin()->create();
    $bankQuestion = BankQuestion::factory()->create();
    $campaign = Campaign::factory()->for($admin, 'creator')->create();
    $otherCampaign = Campaign::factory()->for($admin, 'creator')->create();
    $otherSection = CampaignSection::factory()->for($otherCampaign)->create();

    $this->actingAs($admin)
        ->from(route('admin.campaigns.show', $campaign))
        ->post(route('admin.campaigns.questions.import', $campaign), [
            'bank_question_id' => $bankQuestion->id,
            'campaign_section_id' => $otherSection->id,
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertSessionHasErrors('campaign_section_id')
        ->assertRedirect(route('admin.campaigns.show', $campaign));

    expect($campaign->questions()->exists())->toBeFalse();
});

test('candidate cannot access question library management', function (string $route, string $method) {
    $candidate = User::factory()->candidate()->create();
    $questionBank = QuestionBank::factory()->create();

    $url = match ($route) {
        'admin.question-banks.index', 'admin.question-banks.create', 'admin.question-banks.store' => route($route),
        default => route($route, $questionBank),
    };

    $this->actingAs($candidate)
        ->call($method, $url)
        ->assertForbidden();
})->with([
    ['admin.question-banks.index', 'GET'],
    ['admin.question-banks.create', 'GET'],
    ['admin.question-banks.store', 'POST'],
    ['admin.question-banks.show', 'GET'],
    ['admin.question-banks.edit', 'GET'],
    ['admin.question-banks.update', 'PATCH'],
    ['admin.question-banks.destroy', 'DELETE'],
]);
