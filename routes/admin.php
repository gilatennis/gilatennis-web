<?php

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\BracketController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TennisMatchController;
use App\Http\Controllers\Admin\TennisMatchScoringController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('admin', '/admin/dashboard');

Route::get('admin/login', fn () => Inertia::render('Admin/Login', [
    'status' => session('status'),
]))->name('admin.login');

Route::post('admin/login', [AdminLoginController::class, 'store'])
    ->middleware(array_filter([config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null]))
    ->name('admin.login.store');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

    Route::get('tournaments/{tournament}/registrations', [RegistrationController::class, 'index'])
        ->name('tournaments.registrations.index');

    Route::patch('tournaments/{tournament}/registrations/{registration}/payment', [RegistrationController::class, 'updatePayment'])
        ->name('tournaments.registrations.payment');

    Route::get('tournaments/{tournament}/divisions/{division}/registrations/{registration}', [RegistrationController::class, 'show'])
        ->scopeBindings()
        ->name('tournaments.divisions.registrations.show');

    Route::patch('tournaments/{tournament}/divisions/{division}/registrations/{registration}/assessments', [RegistrationController::class, 'updateAssessments'])
        ->scopeBindings()
        ->name('tournaments.divisions.registrations.assessments.update');

    Route::get('tournaments/{tournament}/divisions/{division}', [DivisionController::class, 'show'])
        ->scopeBindings()
        ->name('tournaments.divisions.show');

    Route::post('divisions/validate', [DivisionController::class, 'validateDivision'])
        ->name('tournaments.divisions.validate');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket', [BracketController::class, 'store'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.store');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/groups', [BracketController::class, 'storeGroups'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.groups.store');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/groups/{group}/entrants', [BracketController::class, 'addGroupEntrant'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.groups.entrants.store');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/groups/{group}/entrants/move', [BracketController::class, 'moveGroupEntrant'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.groups.entrants.move');

    Route::patch('tournaments/{tournament}/divisions/{division}/bracket/groups/{group}/settings', [BracketController::class, 'updateGroupSettings'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.groups.settings.update');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/groups/{group}/winners', [BracketController::class, 'setGroupWinners'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.groups.winners.store');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/promote', [BracketController::class, 'promote'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.promote');

    Route::delete('tournaments/{tournament}/divisions/{division}/bracket', [BracketController::class, 'destroy'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.destroy');

    Route::patch('tournaments/{tournament}/divisions/{division}/bracket/seeds', [BracketController::class, 'seed'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.seed');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/slots/{slot}/match', [BracketController::class, 'startMatch'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.slots.match.store');

    Route::post('tournaments/{tournament}/divisions/{division}/bracket/slots/{slot}/rubbers', [BracketController::class, 'addRubber'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.slots.rubbers.store');

    Route::patch('tournaments/{tournament}/divisions/{division}/bracket/slots/{slot}', [BracketController::class, 'updateSlot'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.slots.update');

    Route::delete('tournaments/{tournament}/divisions/{division}/bracket/slots/{slot}/winner', [BracketController::class, 'undoSlot'])
        ->scopeBindings()
        ->name('tournaments.divisions.bracket.slots.undo');

    Route::resource('tournaments', TournamentController::class);

    Route::get('settings', [SystemController::class, 'edit'])->name('settings');
    Route::patch('settings', [SystemController::class, 'update'])->name('settings.update');

    Route::resource('tennis-matches', TennisMatchController::class)
        ->only(['index', 'create', 'store', 'show', 'update'])
        ->parameters(['tennis-matches' => 'match']);

    Route::post('tennis-matches/{match}/points', [TennisMatchScoringController::class, 'point'])
        ->name('tennis-matches.points');

    Route::post('tennis-matches/{match}/server', [TennisMatchScoringController::class, 'server'])
        ->name('tennis-matches.server');

    Route::post('tennis-matches/{match}/undo', [TennisMatchScoringController::class, 'undo'])
        ->name('tennis-matches.undo');

    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('manage-users');
});
