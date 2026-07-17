<?php

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use App\Models\CandidateApplication;
use App\Models\ExamSession;
use App\Models\User;
use App\QuestionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('candidate uploads a resume before starting an exam', function () {
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    $invitation = assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $response = $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => resumePdfUpload(),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    $application = CandidateApplication::query()
        ->whereBelongsTo($invitation, 'invitation')
        ->sole();

    expect($application)
        ->resume_original_name->toBe('resume.pdf')
        ->resume_uploaded_at->not->toBeNull()
        ->locked_at->toBeNull();

    Storage::disk('r2-private')->assertExists($application->resume_path);
});

test('candidate can replace a resume before starting an exam', function () {
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => resumePdfUpload('First resume'),
        ])
        ->assertSessionHasNoErrors();

    $application = CandidateApplication::query()->sole();
    $previousPath = $application->resume_path;

    $replacement = UploadedFile::fake()->createWithContent(
        'updated-resume.pdf',
        resumePdfContent('Updated resume'),
    );

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => $replacement,
        ])
        ->assertSessionHasNoErrors();

    $application->refresh();

    expect($application->resume_original_name)->toBe('updated-resume.pdf')
        ->and($application->resume_path)->not->toBe($previousPath);

    Storage::disk('r2-private')->assertMissing($previousPath);
    Storage::disk('r2-private')->assertExists($application->resume_path);
});

test('candidate keeps a separate resume application for each campaign', function () {
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaigns = Campaign::factory()->active()->count(2)->create();

    $invitations = $campaigns->map(function (Campaign $campaign) use ($candidate): CampaignInvitation {
        $invitation = assignCandidateToCampaignExam($candidate, $campaign);
        $section = CampaignSection::factory()->for($campaign)->create();
        CampaignQuestion::factory()
            ->for($campaign)
            ->for($section, 'section')
            ->create(['status' => QuestionStatus::Approved]);

        return $invitation;
    });

    foreach ($campaigns as $index => $campaign) {
        $resume = UploadedFile::fake()->createWithContent(
            "campaign-{$campaign->id}.pdf",
            resumePdfContent("Campaign {$campaign->id} resume"),
        );

        $this->actingAs($candidate)
            ->post(route('candidate.campaigns.application.resume.store', $campaign), [
                'resume' => $resume,
            ])
            ->assertSessionHasNoErrors();

        expect(CandidateApplication::query()
            ->where('campaign_invitation_id', $invitations[$index]->id)
            ->value('resume_original_name'))
            ->toBe("campaign-{$campaign->id}.pdf");
    }

    expect(CandidateApplication::query()->count())->toBe(2)
        ->and(CandidateApplication::query()->pluck('resume_path')->unique())->toHaveCount(2);
});

test('candidate exam page requires a resume before showing the start action', function () {
    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $this->actingAs($candidate)
        ->get(route('candidate.campaigns.exam', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/exam')
            ->where('state', 'resume_required')
        );

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertSessionHasErrors('resume');

    expect(ExamSession::query()->doesntExist())->toBeTrue();
});

test('candidate resume is locked when the exam starts', function () {
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => resumePdfUpload(),
        ])
        ->assertSessionHasNoErrors();

    startCandidateExamSession($candidate, $campaign);

    expect(CandidateApplication::query()->sole()->locked_at)->not->toBeNull();

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => resumePdfUpload('Replacement after start'),
        ])
        ->assertSessionHasErrors('resume');
});

test('resume upload only accepts PDF files', function () {
    Storage::fake('r2-private');

    $candidate = User::factory()->create();
    $campaign = Campaign::factory()->active()->create();
    assignCandidateToCampaignExam($candidate, $campaign);
    $section = CampaignSection::factory()->for($campaign)->create();
    CampaignQuestion::factory()
        ->for($campaign)
        ->for($section, 'section')
        ->create(['status' => QuestionStatus::Approved]);

    $this->actingAs($candidate)
        ->post(route('candidate.campaigns.application.resume.store', $campaign), [
            'resume' => UploadedFile::fake()->create('resume.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('resume');

    expect(CandidateApplication::query()->doesntExist())->toBeTrue();
});
