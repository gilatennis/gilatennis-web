<?php

use Illuminate\Support\Facades\Route;

Route::inertia('dashboard', 'Dashboard')->name('dashboard')->middleware('auth');
Route::inertia('/', 'Home')->name('home');
