<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Models\ApplicationSetting;
use App\Models\Assessment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config()->set('assessment.qwen.fake', true);
    config()->set('assessment.threshold', 75);
    AssessmentCriticAgent::fake([
        [
            'outcome' => 'passed',
            'summary' => 'Assessment package is consistent and safe for review.',
            'findings' => [],
            'manual_review_required' => false,
            'repaired_email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);
});

test('admin can view assessment settings with config fallback', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.assessment-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assessment-settings/edit')
            ->where('settings.passing_score', 75)
            ->where('settings.config_default_passing_score', 75),
        );
});

test('admin can update assessment passing score', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.assessment-settings.edit'))
        ->patch(route('admin.assessment-settings.update'), [
            'passing_score' => 90,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessment-settings.edit'));

    expect(ApplicationSetting::integer(ApplicationSetting::AssessmentPassingScore, 75))->toBe(90);
});

test('assessment settings accepts boundary passing scores', function (int $passingScore) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.assessment-settings.edit'))
        ->patch(route('admin.assessment-settings.update'), [
            'passing_score' => $passingScore,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.assessment-settings.edit'));

    expect(ApplicationSetting::integer(ApplicationSetting::AssessmentPassingScore, 75))
        ->toBe($passingScore);
})->with([0, 100]);

test('assessment settings validates passing score range', function (mixed $passingScore) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.assessment-settings.edit'))
        ->patch(route('admin.assessment-settings.update'), [
            'passing_score' => $passingScore,
        ])
        ->assertSessionHasErrors('passing_score')
        ->assertRedirect(route('admin.assessment-settings.edit'));
})->with([
    -1,
    101,
    'abc',
    null,
]);

test('candidate cannot access assessment settings', function (string $route, string $method) {
    $candidate = User::factory()->candidate()->create();

    $this->actingAs($candidate)
        ->call($method, route($route), [
            'passing_score' => 90,
        ])
        ->assertForbidden();
})->with([
    ['admin.assessment-settings.edit', 'GET'],
    ['admin.assessment-settings.update', 'PATCH'],
]);

test('stored passing score overrides config during evaluation', function () {
    ApplicationSetting::setInteger(ApplicationSetting::AssessmentPassingScore, 90);
    config()->set('ai.providers.qwen.key', 'test-qwen-key');

    AssessmentEvaluatorAgent::fake([
        [
            'score' => 82,
            'justification' => 'The answers are solid but below the configured threshold.',
            'email' => [
                'subject' => null,
                'body' => null,
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain indexes.',
                    'rubric' => 'Mentions reads, writes, and storage tradeoffs.',
                    'answer' => str_repeat('This answer explains the tradeoffs clearly. ', 4),
                ],
            ],
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::Evaluated)
        ->ai_score->toBe(82);
});
