<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('admin', '/admin/dashboard');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
});
