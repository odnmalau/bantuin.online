<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use App\QuestionType;
use App\Services\CandidateRankingCalculator;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('assessment.threshold', 75);
    config()->set('assessment.ranking.weights', [
        'resume_score' => 0,
        'assessment_score' => 100,
    ]);
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

test('ranking calculator does not rank from resume score when assessment is unavailable', function () {
    $assessment = Assessment::factory()->create([
        'resume_score' => 80,
    ]);

    $ranking = app(CandidateRankingCalculator::class)->calculate($assessment, null);

    expect($ranking['score'])->toBeNull()
        ->and($ranking['payload']['components'])->toMatchArray([
            'resume_score' => 80,
            'assessment_score' => null,
        ])
        ->and($ranking['payload']['normalized_weights'])->toBe([])
        ->and($ranking['payload']['missing_components'])->toBe(['assessment_score'])
        ->and($ranking['payload']['weighting_mode'])->toBe('unavailable');
});

test('ranking calculator ignores legacy campaign weights and uses assessment score', function () {
    $campaign = Campaign::factory()->create([
        'ranking_weights' => [
            'resume_score' => 40,
            'assessment_score' => 60,
        ],
    ]);

    $assessment = Assessment::factory()->for($campaign)->create([
        'resume_score' => 80,
    ]);

    $ranking = app(CandidateRankingCalculator::class)->calculate($assessment, 100);

    expect($ranking['score'])->toBe(100)
        ->and($ranking['payload']['configured_weights'])->toMatchArray([
            'resume_score' => 0,
            'assessment_score' => 100,
        ])
        ->and($ranking['payload']['weight_source'])->toBe('config_default')
        ->and($ranking['payload']['weighting_mode'])->toBe('assessment_only');
});

test('evaluation job persists open ended assessment scoring fields', function () {
    AssessmentEvaluatorAgent::fake([assessmentEvaluationResponse(86)]);

    $assessment = Assessment::factory()
        ->for(User::factory())
        ->create([
            'resume_score' => 80,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'question' => 'Explain how you would respond to a failed production deployment.',
                    'rubric' => 'Mentions rollback, communication, diagnosis, and prevention.',
                    'type' => QuestionType::LongText->value,
                    'answer' => 'Roll back safely, notify stakeholders, diagnose the failure, and prevent recurrence.',
                    'points' => 10,
                ],
            ],
            'status' => AssessmentStatus::Submitted,
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::EmailSent)
        ->assessment_score->toBe(86)
        ->ranking_score->toBe(86)
        ->and($assessment->ranking_payload['components'])->toMatchArray([
            'resume_score' => 80,
            'assessment_score' => 86,
        ])
        ->and($assessment->ranking_payload['weighting_mode'])->toBe('assessment_only');
});
