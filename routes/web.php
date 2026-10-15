<?php

use App\Http\Controllers\DuitkuReturnController;
use App\Http\Controllers\DuitkuWebhookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentRegistrationController;
use App\Http\Controllers\Umpire\MatchController as UmpireMatchController;
use App\Http\Controllers\Umpire\TournamentController as UmpireTournamentController;
use App\Http\Controllers\Umpire\UmpireLoginController;
use App\Http\Controllers\UmpireScoringController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('search', [SearchController::class, 'index'])->name('search');

// Public umpire scoreboard. A match's unguessable share token is the only
// credential; scoring actions are throttled to blunt link sharing abuse.
Route::get('score/{match:share_token}', [UmpireScoringController::class, 'show'])
    ->name('umpire.score');

Route::prefix('score/{match:share_token}')
    ->name('umpire.score.')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::post('server', [UmpireScoringController::class, 'server'])->name('server');
        Route::post('points', [UmpireScoringController::class, 'point'])->name('points');
        Route::post('undo', [UmpireScoringController::class, 'undo'])->name('undo');
        Route::post('status', [UmpireScoringController::class, 'status'])->name('status');
    });

// Umpire console: a signed-in umpire (or admin) picks a tournament and scores
// the matches a referee has sent to court. Scoring reuses the admin controller.
Route::get('umpire/login', fn () => Inertia::render('Umpire/Login', [
    'status' => session('status'),
]))->name('umpire.login');

Route::post('umpire/login', [UmpireLoginController::class, 'store'])
    ->middleware(array_filter([config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null]))
    ->name('umpire.login.store');

Route::middleware(['umpire', 'verified'])
    ->prefix('umpire')
    ->name('umpire.')
    ->group(function () {
        Route::get('/', [UmpireTournamentController::class, 'index'])->name('tournaments.index');
        Route::get('tournaments/{tournament}', [UmpireTournamentController::class, 'show'])->name('tournaments.show');
        Route::get('matches/{match}', [UmpireMatchController::class, 'show'])->name('matches.show');

        Route::post('matches/{match}/points', [UmpireMatchController::class, 'point'])->name('matches.points');
        Route::post('matches/{match}/server', [UmpireMatchController::class, 'server'])->name('matches.server');
        Route::post('matches/{match}/undo', [UmpireMatchController::class, 'undo'])->name('matches.undo');
        Route::post('matches/{match}/status', [UmpireMatchController::class, 'status'])->name('matches.status');
    });

// Dashboard
Route::inertia('dashboard', 'Dashboard')->name('dashboard')->middleware('auth');

Route::prefix('tournaments')->group(function () {
    Route::get('{tournament:slug}', [TournamentController::class, 'show'])
        ->name('tournaments.show');

    Route::get('{tournament:slug}/live', [TournamentController::class, 'live'])
        ->middleware('throttle:120,1')
        ->name('tournaments.live');

    Route::get('{tournament:slug}/register', [TournamentRegistrationController::class, 'create'])
        ->name('tournaments.register');

    Route::post('{tournament:slug}/register', [TournamentRegistrationController::class, 'store'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('tournaments.register.store');
});

Route::get('registration/{registration:confirmation_code}/success', [TournamentRegistrationController::class, 'success'])
    ->name('tournaments.register.success');

Route::get('registration/{registration:confirmation_code}/failed', [TournamentRegistrationController::class, 'failed'])
    ->name('tournaments.register.failed');

Route::post('webhooks/duitku/callback', [DuitkuWebhookController::class, 'handle'])
    ->name('webhooks.duitku');

Route::get('payments/duitku/return', [DuitkuReturnController::class, 'handle'])
    ->name('payments.duitku.return');
