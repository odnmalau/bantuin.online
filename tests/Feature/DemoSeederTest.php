<?php

use App\AssessmentStatus;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\QuestionBank;
use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates demo users and campaign questions', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@hirepilot.test')->firstOrFail();
    $candidate = User::query()->where('email', 'candidate@hirepilot.test')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($candidate->role)->toBe(UserRole::Candidate)
        ->and($candidate->google_id)->toBe('seed-demo-candidate')
        ->and(Campaign::query()->where('title', 'Backend Engineer Autopilot Campaign')->exists())->toBeTrue()
        ->and(CampaignQuestion::query()->whereHas('campaign', fn ($query) => $query->where('title', 'Backend Engineer Autopilot Campaign'))->count())->toBeGreaterThanOrEqual(4)
        ->and(QuestionBank::query()->where('title', 'Laravel Backend - Mid Level')->exists())->toBeTrue();
});

test('database seeder creates useful demo assessments', function () {
    $this->seed(DatabaseSeeder::class);

    $passedCandidate = User::query()->where('email', 'passed@hirepilot.test')->firstOrFail();
    $reviewCandidate = User::query()->where('email', 'review@hirepilot.test')->firstOrFail();
    $invitedCandidate = User::query()->where('email', 'invited@hirepilot.test')->firstOrFail();
    $campaign = Campaign::query()->where('title', 'Backend Engineer Autopilot Campaign')->firstOrFail();

    expect($passedCandidate->assessments()->sole()->status)->toBe(AssessmentStatus::PendingApproval)
        ->and($passedCandidate->assessments()->sole()->campaign_id)->toBe($campaign->id)
        ->and($passedCandidate->assessments()->sole()->answers_payload[0])->toHaveKeys([
            'campaign_question_id',
            'type',
            'points',
            'section_title',
        ])
        ->and($reviewCandidate->assessments()->sole()->status)->toBe(AssessmentStatus::Evaluated)
        ->and($invitedCandidate->assessments()->sole()->status)->toBe(AssessmentStatus::EmailSent)
        ->and($invitedCandidate->assessments()->sole()->email_sent_at)->not->toBeNull();
});

test('database seeder is idempotent for demo records', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@hirepilot.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'candidate@hirepilot.test')->count())->toBe(1)
        ->and(User::query()->whereIn('email', [
            'passed@hirepilot.test',
            'review@hirepilot.test',
            'invited@hirepilot.test',
        ])->count())->toBe(3)
        ->and(Campaign::query()->where('title', 'Backend Engineer Autopilot Campaign')->count())->toBe(1)
        ->and(QuestionBank::query()->where('title', 'Laravel Backend - Mid Level')->count())->toBe(1)
        ->and(CampaignQuestion::query()->whereHas('campaign', fn ($query) => $query->where('title', 'Backend Engineer Autopilot Campaign'))->count())->toBeGreaterThanOrEqual(4)
        ->and(demoAssessmentCount())->toBe(3);
});

function demoAssessmentCount(): int
{
    return User::query()
        ->whereIn('email', [
            'passed@hirepilot.test',
            'review@hirepilot.test',
            'invited@hirepilot.test',
        ])
        ->withCount('assessments')
        ->get()
        ->sum('assessments_count');
}
