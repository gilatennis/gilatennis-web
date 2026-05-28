<?php

use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TournamentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('admin', '/admin/dashboard');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

    Route::get('tournaments/{tournament}/registrations', [RegistrationController::class, 'index'])
        ->name('tournaments.registrations.index');

    Route::patch('tournaments/{tournament}/registrations/{registration}/payment', [RegistrationController::class, 'updatePayment'])
        ->name('tournaments.registrations.payment');

    Route::get('tournaments/{tournament}/divisions/{division}', [DivisionController::class, 'show'])
        ->name('tournaments.divisions.show');

    Route::resource('tournaments', TournamentController::class);

    Route::get('settings', [SystemController::class, 'edit'])->name('settings');
    Route::patch('settings', [SystemController::class, 'update'])->name('settings.update');
});
