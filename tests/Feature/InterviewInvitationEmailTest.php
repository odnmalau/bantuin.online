<?php

use App\AssessmentStatus;
use App\Jobs\SendInterviewInvitationEmail;
use App\Mail\InterviewInvitationMail;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

test('approved assessment sends interview invitation email to candidate', function () {
    Mail::fake();

    $candidate = User::factory()->candidate()->create([
        'email' => 'candidate@example.com',
    ]);
    $assessment = Assessment::factory()
        ->approved()
        ->for($candidate)
        ->create();

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertSent(InterviewInvitationMail::class, function (InterviewInvitationMail $mail) use ($candidate): bool {
        return $mail->hasTo($candidate->email)
            && $mail->subjectLine === 'Final interview invitation'
            && $mail->body === 'Final email body from Admin.';
    });
});

test('interview invitation mailable uses final admin subject and body', function () {
    $mailable = new InterviewInvitationMail(
        subjectLine: 'Final subject from Admin',
        body: "Hello Candidate,\n\nPlease continue to interview.",
    );

    $mailable->assertHasSubject('Final subject from Admin');
    $mailable->assertSeeInText('Hello Candidate,');
    $mailable->assertSeeInText('Please continue to interview.');
});

test('successful email job marks assessment as email sent', function () {
    Mail::fake();

    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory()->candidate())
        ->create();

    (new SendInterviewInvitationEmail($assessment))->handle();

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailSent)
        ->email_sent_at->not->toBeNull();
});

test('email failure marks assessment as email failed', function () {
    Log::spy();

    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new RuntimeException('Transport failed.'));

    $assessment = Assessment::factory()
        ->approved()
        ->for(User::factory()->candidate())
        ->create();

    (new SendInterviewInvitationEmail($assessment))->handle();

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailFailed)
        ->email_sent_at->toBeNull();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Interview invitation email failed.', Mockery::on(fn (array $context): bool => $context['assessment_id'] === $assessment->id
            && $context['candidate_id'] === $assessment->user_id
            && $context['exception'] === RuntimeException::class));
});

test('email is not sent before explicit admin approval', function () {
    Log::spy();
    Mail::fake();

    $assessment = Assessment::factory()
        ->for(User::factory()->candidate())
        ->create([
            'status' => AssessmentStatus::PendingApproval,
            'approved_email_subject' => null,
            'approved_email_body' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);

    (new SendInterviewInvitationEmail($assessment))->handle();

    Mail::assertNothingSent();

    expect($assessment->refresh())
        ->status->toBe(AssessmentStatus::EmailFailed)
        ->email_sent_at->toBeNull();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Interview invitation email skipped because assessment is not sendable.', Mockery::on(fn (array $context): bool => $context['assessment_id'] === $assessment->id
            && $context['candidate_id'] === $assessment->user_id
            && $context['status'] === AssessmentStatus::PendingApproval->value));
});
