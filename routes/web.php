<?php

use App\Http\Controllers\DuitkuReturnController;
use App\Http\Controllers\DuitkuWebhookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('search', [SearchController::class, 'index'])->name('search');

// Dashboard
Route::inertia('dashboard', 'Dashboard')->name('dashboard')->middleware('auth');

Route::prefix('tournaments')->group(function () {
    Route::get('{tournament:slug}', [TournamentController::class, 'show'])
        ->name('tournaments.show');

    Route::get('{tournament:slug}/register', [TournamentRegistrationController::class, 'create'])
        ->name('tournaments.register');

    Route::post('{tournament:slug}/register', [TournamentRegistrationController::class, 'store'])
        ->name('tournaments.register.store');
});

Route::get('registration/{registration:confirmation_code}/success', [TournamentRegistrationController::class, 'success'])
    ->name('tournaments.register.success');

Route::get('registration/{registration:confirmation_code}/failed', [TournamentRegistrationController::class, 'failed'])
    ->name('tournaments.register.failed');

Route::post('webhooks/duitku/callback', [DuitkuWebhookController::class, 'handle'])
    ->name('webhooks.duitku')
    ->withoutMiddleware(['csrf']);

Route::get('payments/duitku/return', [DuitkuReturnController::class, 'handle'])
    ->name('payments.duitku.return');
