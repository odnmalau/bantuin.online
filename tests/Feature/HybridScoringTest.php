<?php

use App\Ai\Agents\AssessmentCriticAgent;
use App\Ai\Agents\AssessmentEvaluatorAgent;
use App\AssessmentStatus;
use App\Jobs\EvaluateAssessmentWithAi;
use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\User;
use App\QuestionGradingMode;
use App\QuestionType;
use App\Services\CandidateRankingCalculator;
use App\Services\DeterministicAssessmentGrader;

beforeEach(function () {
    $this->withoutVite();
    config()->set('ai.providers.qwen.key', 'test-qwen-key');
    config()->set('assessment.threshold', 75);
    config()->set('assessment.ranking.weights', [
        'resume_score' => 35,
        'essay_score' => 50,
        'mcq_score' => 15,
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

test('deterministic grader scores objective question snapshots', function () {
    $assessment = Assessment::factory()->create([
        'answers_payload' => [
            [
                'type' => QuestionType::MultipleChoice->value,
                'answer' => ['database', 'queue'],
                'correct_answer' => ['queue', 'database'],
                'points' => 2,
            ],
            [
                'type' => QuestionType::YesNo->value,
                'answer' => ' yes ',
                'correct_answer' => ['Yes'],
                'points' => 1,
            ],
            [
                'type' => QuestionType::FillBlank->value,
                'answer' => 'PostgreSQL',
                'correct_answer' => ['postgres', 'postgresql'],
                'points' => 1,
            ],
            [
                'type' => QuestionType::MatchingPairs->value,
                'answer' => [
                    'queue' => 'async jobs',
                    'index' => 'read speed',
                ],
                'correct_answer' => [
                    'index' => 'read speed',
                    'queue' => 'async jobs',
                ],
                'points' => 2,
            ],
            [
                'type' => QuestionType::MultipleChoice->value,
                'grading_mode' => QuestionGradingMode::Manual->value,
                'answer' => 'right',
                'correct_answer' => ['right'],
                'points' => 100,
            ],
            [
                'type' => QuestionType::MultipleChoice->value,
                'answer' => 'wrong',
                'correct_answer' => ['right'],
                'points' => 2,
            ],
            [
                'type' => QuestionType::LongText->value,
                'answer' => 'Essay answer.',
                'correct_answer' => null,
                'points' => 10,
            ],
        ],
    ]);

    expect(app(DeterministicAssessmentGrader::class)->grade($assessment))->toBe(75);
});

test('ranking calculator normalizes missing component weights', function () {
    $assessment = Assessment::factory()->create([
        'resume_score' => 80,
    ]);

    $ranking = app(CandidateRankingCalculator::class)->calculate($assessment, null, 90);

    expect($ranking['score'])->toBe(86)
        ->and($ranking['payload']['components'])->toMatchArray([
            'resume_score' => 80,
            'essay_score' => 90,
            'mcq_score' => null,
        ])
        ->and($ranking['payload']['normalized_weights']['resume_score'])->toBe(41.1765)
        ->and($ranking['payload']['normalized_weights']['essay_score'])->toBe(58.8235)
        ->and($ranking['payload']['missing_components'])->toBe(['mcq_score'])
        ->and($ranking['payload']['weighting_mode'])->toBe('normalized_available_components');
});

test('ranking calculator uses campaign configured weights', function () {
    $campaign = Campaign::factory()->create([
        'ranking_weights' => [
            'resume_score' => 50,
            'essay_score' => 30,
            'mcq_score' => 20,
        ],
    ]);

    $assessment = Assessment::factory()->for($campaign)->create([
        'resume_score' => 80,
    ]);

    $ranking = app(CandidateRankingCalculator::class)->calculate($assessment, 60, 100);

    expect($ranking['score'])->toBe(82)
        ->and($ranking['payload']['configured_weights'])->toMatchArray([
            'resume_score' => 50,
            'essay_score' => 30,
            'mcq_score' => 20,
        ])
        ->and($ranking['payload']['weight_source'])->toBe('campaign');
});

test('deterministic grader calculates weighted section scores when section metadata exists', function () {
    $assessment = Assessment::factory()->create([
        'answers_payload' => [
            [
                'section_id' => 10,
                'section_title' => 'Knowledge Check',
                'section_weight' => 25,
                'type' => QuestionType::MultipleChoice->value,
                'answer' => 'A',
                'correct_answer' => ['A'],
                'points' => 1,
            ],
            [
                'section_id' => 20,
                'section_title' => 'Practical Reasoning',
                'section_weight' => 75,
                'type' => QuestionType::MultipleChoice->value,
                'answer' => 'wrong',
                'correct_answer' => ['right'],
                'points' => 1,
            ],
        ],
    ]);

    $breakdown = app(DeterministicAssessmentGrader::class)->breakdown($assessment);

    expect($breakdown['score'])->toBe(25)
        ->and($breakdown['section_scores'])->toHaveCount(2)
        ->and($breakdown['section_scores'][0])->toMatchArray([
            'section_id' => 10,
            'title' => 'Knowledge Check',
            'weight' => 25,
            'score' => 100,
        ])
        ->and($breakdown['section_scores'][1])->toMatchArray([
            'section_id' => 20,
            'title' => 'Practical Reasoning',
            'weight' => 75,
            'score' => 0,
        ]);
});

test('evaluation job persists hybrid scoring fields', function () {
    AssessmentEvaluatorAgent::fake([
        [
            'score' => 86,
            'justification' => 'The essay answer matches the rubric.',
            'email' => [
                'subject' => 'Interview Invitation - Candidate',
                'body' => 'Thank you for completing the assessment. We would like to invite you to continue to the interview stage.',
            ],
        ],
    ]);

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'resume_score' => 80,
            'answers_payload' => [
                [
                    'question_id' => 1,
                    'section_id' => 10,
                    'section_title' => 'Knowledge Check',
                    'section_weight' => 100,
                    'question' => 'Which datastore supports relational constraints?',
                    'rubric' => 'Select PostgreSQL.',
                    'type' => QuestionType::MultipleChoice->value,
                    'answer' => 'PostgreSQL',
                    'correct_answer' => ['PostgreSQL'],
                    'points' => 1,
                ],
                [
                    'question_id' => 2,
                    'question' => 'Explain queue retries.',
                    'rubric' => 'Mentions backoff and failed jobs.',
                    'type' => QuestionType::LongText->value,
                    'answer' => 'Retries should use backoff and failed jobs for inspection.',
                    'correct_answer' => null,
                    'points' => 1,
                ],
            ],
            'status' => AssessmentStatus::Submitted,
        ]);

    app()->call([(new EvaluateAssessmentWithAi($assessment)), 'handle']);

    $assessment->refresh();

    expect($assessment)
        ->status->toBe(AssessmentStatus::PendingApproval)
        ->ai_score->toBe(86)
        ->essay_score->toBe(86)
        ->mcq_score->toBe(100)
        ->ranking_score->toBe(86)
        ->and($assessment->ranking_payload['components'])->toMatchArray([
            'resume_score' => 80,
            'essay_score' => 86,
            'mcq_score' => 100,
        ])
        ->and($assessment->ranking_payload['section_scores'][0])->toMatchArray([
            'section_id' => 10,
            'title' => 'Knowledge Check',
            'score' => 100,
        ])
        ->and($assessment->ranking_payload['weighting_mode'])->toBe('configured_weights');
});
