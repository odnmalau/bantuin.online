<?php

use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\AssessmentSettingsController;
use App\Http\Controllers\Admin\BankQuestionController;
use App\Http\Controllers\Admin\CampaignAssessmentGenerationController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CampaignInvitationController;
use App\Http\Controllers\Admin\CampaignQuestionController;
use App\Http\Controllers\Admin\CampaignQuestionImportController;
use App\Http\Controllers\Admin\CampaignSectionController;
use App\Http\Controllers\Admin\QuestionBankController;
use App\Http\Controllers\Admin\QuestionBankQuestionGenerationController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CampaignInviteController;
use App\Http\Controllers\Candidate\AssessmentController;
use App\Http\Controllers\Candidate\ExamSessionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::get('invites/{token}', [CampaignInviteController::class, 'show'])->name('invites.show');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/publish', [CampaignController::class, 'publish'])->name('campaigns.publish');
        Route::post('campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])->name('campaigns.invitations.store');
        Route::post('campaigns/{campaign}/generate-assessment', [CampaignAssessmentGenerationController::class, 'store'])->name('campaigns.generate-assessment');
        Route::post('campaigns/{campaign}/sections', [CampaignSectionController::class, 'store'])->name('campaigns.sections.store');
        Route::delete('campaigns/{campaign}/sections/{section}', [CampaignSectionController::class, 'destroy'])->name('campaigns.sections.destroy');
        Route::post('campaigns/{campaign}/questions', [CampaignQuestionController::class, 'store'])->name('campaigns.questions.store');
        Route::post('campaigns/{campaign}/questions/import', [CampaignQuestionImportController::class, 'store'])->name('campaigns.questions.import');
        Route::post('campaigns/{campaign}/questions/approve-all', [CampaignQuestionController::class, 'approveAll'])->name('campaigns.questions.approve-all');
        Route::post('campaigns/{campaign}/questions/{question}/approve', [CampaignQuestionController::class, 'approve'])->name('campaigns.questions.approve');
        Route::post('campaigns/{campaign}/questions/{question}/regenerate-mcq-options', [CampaignQuestionController::class, 'regenerateMcqOptions'])->name('campaigns.questions.regenerate-mcq-options');
        Route::post('campaigns/{campaign}/questions/{question}/convert-to-mcq', [CampaignQuestionController::class, 'convertToMcq'])->name('campaigns.questions.convert-to-mcq');
        Route::patch('campaigns/{campaign}/questions/{question}', [CampaignQuestionController::class, 'update'])->name('campaigns.questions.update');
        Route::delete('campaigns/{campaign}/questions/{question}', [CampaignQuestionController::class, 'destroy'])->name('campaigns.questions.destroy');

        Route::resource('question-banks', QuestionBankController::class);
        Route::post('question-banks/{questionBank}/generate-questions', [QuestionBankQuestionGenerationController::class, 'store'])->name('question-banks.generate-questions');
        Route::get('question-banks/{questionBank}/questions/create', [BankQuestionController::class, 'create'])->name('question-banks.questions.create');
        Route::post('question-banks/{questionBank}/questions', [BankQuestionController::class, 'store'])->name('question-banks.questions.store');
        Route::get('question-banks/{questionBank}/questions/{bankQuestion}/edit', [BankQuestionController::class, 'edit'])->name('question-banks.questions.edit');
        Route::patch('question-banks/{questionBank}/questions/{bankQuestion}', [BankQuestionController::class, 'update'])->name('question-banks.questions.update');
        Route::post('question-banks/{questionBank}/questions/{bankQuestion}/regenerate-mcq-options', [BankQuestionController::class, 'regenerateMcqOptions'])->name('question-banks.questions.regenerate-mcq-options');
        Route::post('question-banks/{questionBank}/questions/{bankQuestion}/convert-to-mcq', [BankQuestionController::class, 'convertToMcq'])->name('question-banks.questions.convert-to-mcq');
        Route::delete('question-banks/{questionBank}/questions/{bankQuestion}', [BankQuestionController::class, 'destroy'])->name('question-banks.questions.destroy');

        Route::get('rankings', [RankingController::class, 'index'])->name('rankings.index');

        Route::get('assessments', [AdminAssessmentController::class, 'index'])->name('assessments.index');
        Route::get('assessments/{assessment}', [AdminAssessmentController::class, 'show'])->name('assessments.show');
        Route::post('assessments/{assessment}/retry-evaluation', [AdminAssessmentController::class, 'retryEvaluation'])->name('assessments.retry-evaluation');
        Route::post('assessments/{assessment}/retry-email', [AdminAssessmentController::class, 'retryEmail'])->name('assessments.retry-email');
        Route::post('assessments/{assessment}/promote', [AdminAssessmentController::class, 'promote'])->name('assessments.promote');
        Route::post('assessments/{assessment}/override-score', [AdminAssessmentController::class, 'overrideScore'])->name('assessments.override-score');
        Route::post('assessments/{assessment}/approve', [AdminAssessmentController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject', [AdminAssessmentController::class, 'reject'])->name('assessments.reject');

        Route::get('assessment-settings', [AssessmentSettingsController::class, 'edit'])->name('assessment-settings.edit');
        Route::patch('assessment-settings', [AssessmentSettingsController::class, 'update'])->name('assessment-settings.update');
    });

Route::middleware(['auth', 'role:candidate'])
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
