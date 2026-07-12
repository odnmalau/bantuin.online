<?php

use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\CampaignAssessmentGenerationController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CampaignInvitationController;
use App\Http\Controllers\Admin\CampaignQuestionController;
use App\Http\Controllers\Admin\CampaignRankingController;
use App\Http\Controllers\Admin\CampaignSectionController;
use App\Http\Controllers\Admin\CampaignStatusController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CampaignInviteController;
use App\Http\Controllers\Candidate\AssessmentController;
use App\Http\Controllers\Candidate\ExamSessionController;
use App\Http\Controllers\CurrentTeamController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OwnershipTransferController;
use App\Http\Controllers\Support\OwnershipTransferController as SupportOwnershipTransferController;
use App\Http\Controllers\Support\TeamController as SupportTeamController;
use App\Http\Controllers\Support\TeamLifecycleController as SupportTeamLifecycleController;
use App\Http\Controllers\Support\TeamMembershipRepairController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMembershipController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::get('invites/{token}', [CampaignInviteController::class, 'show'])->name('invites.show');
Route::get('team-invitations/{token}', [TeamInvitationController::class, 'show'])->name('team-invitations.show');
Route::get('ownership-transfers/{token}', [OwnershipTransferController::class, 'show'])->name('ownership-transfers.show');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::post('teams/{team}/deactivate', [TeamController::class, 'deactivate'])->name('teams.deactivate');
    Route::post('teams/{team}/reactivate', [TeamController::class, 'reactivate'])->name('teams.reactivate');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::put('current-team', CurrentTeamController::class)->name('current-team.update');
    Route::post('team-invitations', [TeamInvitationController::class, 'store'])->name('team-invitations.store');
    Route::delete('team-invitations/{teamInvitation}', [TeamInvitationController::class, 'destroy'])->name('team-invitations.destroy');
    Route::post('team-invitations/{teamInvitation}/resend', [TeamInvitationController::class, 'resend'])->name('team-invitations.resend');
    Route::delete('team-memberships/current', [TeamMembershipController::class, 'leave'])->name('team-memberships.leave');
    Route::patch('team-memberships/{teamMembership}', [TeamMembershipController::class, 'update'])->name('team-memberships.update');
    Route::delete('team-memberships/{teamMembership}', [TeamMembershipController::class, 'destroy'])->name('team-memberships.destroy');
    Route::post('ownership-transfers', [OwnershipTransferController::class, 'store'])->name('ownership-transfers.store');
    Route::delete('ownership-transfers/{ownershipTransfer}', [OwnershipTransferController::class, 'destroy'])->name('ownership-transfers.destroy');
});

Route::middleware(['auth', 'current-team'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/publish', [CampaignController::class, 'publish'])->name('campaigns.publish');
        Route::post('campaigns/{campaign}/archive', [CampaignStatusController::class, 'archive'])->name('campaigns.archive');
        Route::post('campaigns/{campaign}/draft', [CampaignStatusController::class, 'draft'])->name('campaigns.draft');
        Route::patch('campaigns/{campaign}/ranking', [CampaignRankingController::class, 'update'])->name('campaigns.ranking.update');
        Route::post('campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])->name('campaigns.invitations.store');
        Route::post('campaigns/{campaign}/generate-assessment', [CampaignAssessmentGenerationController::class, 'store'])->name('campaigns.generate-assessment');
        Route::post('campaigns/{campaign}/sections', [CampaignSectionController::class, 'store'])->name('campaigns.sections.store');
        Route::delete('campaigns/{campaign}/sections/{section}', [CampaignSectionController::class, 'destroy'])->name('campaigns.sections.destroy');
        Route::post('campaigns/{campaign}/questions', [CampaignQuestionController::class, 'store'])->name('campaigns.questions.store');
        Route::post('campaigns/{campaign}/questions/approve-all', [CampaignQuestionController::class, 'approveAll'])->name('campaigns.questions.approve-all');
        Route::post('campaigns/{campaign}/questions/{question}/approve', [CampaignQuestionController::class, 'approve'])->name('campaigns.questions.approve');
        Route::post('campaigns/{campaign}/questions/{question}/regenerate-mcq-options', [CampaignQuestionController::class, 'regenerateMcqOptions'])->name('campaigns.questions.regenerate-mcq-options');
        Route::post('campaigns/{campaign}/questions/{question}/convert-to-mcq', [CampaignQuestionController::class, 'convertToMcq'])->name('campaigns.questions.convert-to-mcq');
        Route::patch('campaigns/{campaign}/questions/{question}', [CampaignQuestionController::class, 'update'])->name('campaigns.questions.update');
        Route::delete('campaigns/{campaign}/questions/{question}', [CampaignQuestionController::class, 'destroy'])->name('campaigns.questions.destroy');

        Route::get('rankings', [RankingController::class, 'index'])->name('rankings.index');

        Route::get('assessments/{assessment}', [AdminAssessmentController::class, 'show'])->name('assessments.show');
        Route::post('assessments/{assessment}/retry-evaluation', [AdminAssessmentController::class, 'retryEvaluation'])->name('assessments.retry-evaluation');
        Route::post('assessments/{assessment}/retry-email', [AdminAssessmentController::class, 'retryEmail'])->name('assessments.retry-email');
        Route::post('assessments/{assessment}/promote', [AdminAssessmentController::class, 'promote'])->name('assessments.promote');
        Route::post('assessments/{assessment}/override-score', [AdminAssessmentController::class, 'overrideScore'])->name('assessments.override-score');
        Route::post('assessments/{assessment}/approve', [AdminAssessmentController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject', [AdminAssessmentController::class, 'reject'])->name('assessments.reject');
    });

Route::middleware(['auth', 'platform-operator'])->prefix('support')->name('support.')->group(function () {
    Route::get('teams', [SupportTeamController::class, 'index'])->name('teams.index');
    Route::get('teams/{team}', [SupportTeamController::class, 'show'])->name('teams.show');
    Route::post('teams/{team}/membership-repairs', [TeamMembershipRepairController::class, 'store'])->name('teams.membership-repairs.store');
    Route::post('teams/{team}/ownership-transfers', [SupportOwnershipTransferController::class, 'store'])->name('teams.ownership-transfers.store');
    Route::post('teams/{team}/deactivate', [SupportTeamLifecycleController::class, 'deactivate'])->name('teams.deactivate');
    Route::post('teams/{team}/reactivate', [SupportTeamLifecycleController::class, 'reactivate'])->name('teams.reactivate');
});

Route::middleware('auth')
    ->prefix('candidate')
    ->name('candidate.')
    ->group(function () {
        Route::get('exam', [AssessmentController::class, 'redirectExam'])->name('exam');
        Route::get('campaigns/{campaign}/exam', [AssessmentController::class, 'campaignExam'])->name('campaigns.exam');
        Route::post('campaigns/{campaign}/exam-sessions', [ExamSessionController::class, 'store'])->name('campaigns.exam-sessions.store');
        Route::patch('campaigns/{campaign}/exam-sessions/{examSession}', [ExamSessionController::class, 'update'])->name('campaigns.exam-sessions.update');
        Route::post('campaigns/{campaign}/exam-sessions/{examSession}/advance', [ExamSessionController::class, 'advance'])->name('campaigns.exam-sessions.advance');
        Route::post('campaigns/{campaign}/exam-sessions/{examSession}/violations', [ExamSessionController::class, 'storeViolation'])->name('campaigns.exam-sessions.violations.store');
        Route::post('campaigns/{campaign}/exam-sessions/{examSession}/finalize', [ExamSessionController::class, 'finalize'])->name('campaigns.exam-sessions.finalize');
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
    });

require __DIR__.'/settings.php';
