<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::livewire('/candidate-profile', 'pages::candidate-profile')
    ->middleware(['auth', 'candidate'])
    ->name('candidate.profile');
