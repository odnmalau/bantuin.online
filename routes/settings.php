<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::middleware('auth')
    ->prefix('settings')
    ->group(function () {
        Route::redirect('/', '/settings/profile');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

Route::middleware('auth')->group(function () {
    Route::get('settings/appearance', fn (): Response => Inertia::render('settings/appearance'))
        ->name('appearance.edit');
});
