<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::livewire('/jobs', 'pages::job-search')->name('jobs.index');
Route::livewire('/categories/{category:slug}', 'pages::category-show')->name('categories.show');
Route::get('/jobs/{job_posting:slug}', [JobPostingController::class, 'show'])->name('jobs.show');

Route::middleware(['auth', 'candidate'])->group(function () {
    Route::livewire('/jobs/{job_posting:slug}/apply', 'pages::job-apply')->name('jobs.apply');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
