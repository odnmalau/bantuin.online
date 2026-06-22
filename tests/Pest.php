<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function resumePdfContent(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET\n";

    $objects = [
        1 => '<</Type/Catalog/Pages 2 0 R>>',
        2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
        3 => '<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
        5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
    ];

    $pdf = "%PDF-1.4\n";
    $xref = [];

    for ($n = 1; $n <= 5; $n++) {
        $xref[$n] = strlen($pdf);

        if ($n === 4) {
            $pdf .= '4 0 obj<</Length '.strlen($stream).">>stream\n{$stream}endstream\nendobj\n";
        } else {
            $pdf .= "{$n} 0 obj{$objects[$n]}endobj\n";
        }
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";

    for ($n = 1; $n <= 5; $n++) {
        $pdf .= sprintf("%010d 00000 n \n", $xref[$n]);
    }

    $pdf .= "trailer<</Size 6/Root 1 0 R>>\nstartxref\n{$xrefPos}\n%%EOF";

    return $pdf;
}

function resumePdfUpload(string $text = 'Laravel PostgreSQL queue worker experience'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('resume.pdf', resumePdfContent($text));
}

function assignCandidateToCampaignExam(User $candidate, Campaign $campaign, ?User $invitedBy = null): CampaignInvitation
{
    $admin = $invitedBy ?? User::factory()->admin()->create();

    return CampaignInvitation::factory()
        ->for($campaign)
        ->forCandidate($candidate)
        ->accepted($candidate)
        ->create([
            'invited_by' => $admin->id,
        ]);
}

function startCandidateExamSession(User $candidate, Campaign $campaign): ExamSession
{
    test()
        ->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.store', $campaign))
        ->assertRedirect(route('candidate.campaigns.exam', $campaign));

    return ExamSession::query()
        ->where('user_id', $candidate->id)
        ->where('campaign_id', $campaign->id)
        ->firstOrFail();
}

/**
 * @param  array<int|string, string>  $answers
 */
function submitCandidateAssessmentViaExamSession(
    User $candidate,
    Campaign $campaign,
    array $answers,
    ?UploadedFile $resume = null,
): Assessment {
    $resume ??= resumePdfUpload();
    $session = startCandidateExamSession($candidate, $campaign);

    test()->actingAs($candidate)
        ->patch(route('candidate.campaigns.exam-sessions.update', [$campaign, $session]), [
            'answers' => $answers,
        ])
        ->assertRedirect();

    test()->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.advance', [$campaign, $session->fresh()]))
        ->assertRedirect();

    test()->actingAs($candidate)
        ->post(route('candidate.campaigns.exam-sessions.finalize', [$campaign, $session->fresh()]), [
            'resume' => $resume,
        ])
        ->assertRedirect();

    return Assessment::query()
        ->where('user_id', $candidate->id)
        ->where('campaign_id', $campaign->id)
        ->sole();
}

function fakeGoogleAuthConfig(): void
{
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
}

function fakeGoogleRedirect(string $redirectUrl = 'https://accounts.google.com/o/oauth2/auth'): void
{
    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect($redirectUrl));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}

function fakeGoogleUserAuthentication(string $id, string $email, string $name): void
{
    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn($id);
    $googleUser->shouldReceive('getEmail')->andReturn($email);
    $googleUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}
