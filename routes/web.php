<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobListingController;
use App\Http\Controllers\ApplicationController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::resource('job-listings', JobListingController::class)
->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], ['auth','employer']);

Route::resource('applications', ApplicationController::class)
->except(['edit', 'update'])
->middleware(['auth', 'candidate']);

Route::livewire('/candidate-profile', 'pages::candidate-profile')
    ->middleware(['auth', 'candidate'])
    ->name('candidate.profile');

Route::livewire('/employer-profile', 'pages::employer-profile')
    ->middleware(['auth', 'employer'])
    ->name('employer.profile');