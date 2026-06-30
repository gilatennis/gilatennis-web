<?php

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TennisMatchController;
use App\Http\Controllers\Admin\TournamentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('admin', '/admin/dashboard');

Route::get('admin/login', fn() => Inertia::render('Admin/Login', [
    'status' => session('status'),
]))->name('admin.login');

Route::post('admin/login', [AdminLoginController::class, 'store'])
    ->middleware(array_filter([config('fortify.limiters.login') ? 'throttle:' . config('fortify.limiters.login') : null]))
    ->name('admin.login.store');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', fn() => Inertia::render('Admin/Dashboard'))->name('dashboard');

    Route::get('tournaments/{tournament}/registrations', [RegistrationController::class, 'index'])
        ->name('tournaments.registrations.index');

    Route::patch('tournaments/{tournament}/registrations/{registration}/payment', [RegistrationController::class, 'updatePayment'])
        ->name('tournaments.registrations.payment');

    Route::get('tournaments/{tournament}/divisions/{division}', [DivisionController::class, 'show'])
        ->name('tournaments.divisions.show');

    Route::post('divisions/validate', [DivisionController::class, 'validateDivision'])
        ->name('tournaments.divisions.validate');

    Route::resource('tournaments', TournamentController::class);

    Route::get('settings', [SystemController::class, 'edit'])->name('settings');
    Route::patch('settings', [SystemController::class, 'update'])->name('settings.update');

    Route::get('tennis-matches', [TennisMatchController::class, 'index'])->name('tennis-matches.index');
    Route::get('tennis-matches/create', [TennisMatchController::class, 'create'])->name('tennis-matches.create');
});
