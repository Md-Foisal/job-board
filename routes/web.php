<?php

use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\CandidateDashboardController;
use App\Http\Controllers\CandidatePreferenceController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateSavedJobController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::livewire('/jobs', 'pages::job-search')->name('jobs.index');
Route::livewire('/categories/{categoryModel:slug}', 'pages::category-show')->name('categories.show');
Route::get('/jobs/{job_posting:slug}', [JobPostingController::class, 'show'])->name('jobs.show');
Route::get('/companies/{company:slug}', [CompanyController::class, 'show'])->name('companies.show');

Route::middleware(['auth', 'candidate'])->prefix('candidate')->name('candidate.')->group(function () {
    Route::get('/dashboard', [CandidateDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [CandidateProfileController::class, 'update'])->name('profile.update');

    Route::get('/preferences', [CandidatePreferenceController::class, 'edit'])->name('preferences.edit');
    Route::patch('/preferences', [CandidatePreferenceController::class, 'update'])->name('preferences.update');

    // Education records: list + add/edit/delete are all Livewire actions
    // inside this one page component (CRUD-in-modal, claude/14 step 3b) --
    // no separate store/update/destroy HTTP routes are needed the way a
    // plain Blade+Controller resource would need them.
    Route::livewire('/education', 'pages::candidate.education')->name('education.index');

    // Experience records: same CRUD-in-modal pattern as Education (claude/14 step 3b)
    Route::livewire('/experience', 'pages::candidate.experience')->name('experience.index');


    // Document library: same CRUD-in-modal pattern, plus a plain Policy-gated
    // download route (claude/14 step 7 security fix -- a private-disk file must
    // be served through an owner-only check, never a guessable public URL).
    Route::livewire('/documents', 'pages::candidate.documents')->name('documents.index');
    Route::get('/documents/{document}/download', DocumentDownloadController::class)->name('documents.download');

    // Skill selection: unlike Education/Experience/Documents this is a
    // singleton "edit your skill set, then Save" page (claude/14 route 17
    // -- GET/PATCH .edit/.update, "pivot bulk sync"), not incremental
    // per-item CRUD -- so one Route::livewire() name is enough, matching
    // Preferences' edit/update naming even though Livewire handles both
    // verbs through this single route. Still Livewire (not Blade like
    // Preferences) because the search-as-you-type autocomplete needs it.
    Route::livewire('/skills', 'pages::candidate.skills')->name('skills.edit');

    // Both plain Blade+Controller list pages (claude/14 routes 19 & 21) --
    // auto-scoped to the signed-in candidate, no filter/sort requirements,
    // so no Livewire reactivity is needed.
    Route::get('/applications', [CandidateApplicationController::class, 'index'])->name('applications.index');
    Route::get('/saved-jobs', [CandidateSavedJobController::class, 'index'])->name('saved-jobs.index');
});

Route::middleware(['auth', 'candidate'])->group(function () {
    Route::livewire('/jobs/{jobPosting:slug}/apply', 'pages::job-apply')->name('jobs.apply');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Candidates have a real dashboard at candidate.dashboard (claude/14
    // route ১১); this generic /dashboard stays the Fortify post-login
    // 'home' target (config/fortify.php) and the target every existing
    // "Dashboard" link (navbar, sidebar Platform group) already points at,
    // so it dispatches instead of duplicating those links per role.
    // Employer/admin have no dashboard of their own yet, so they still get
    // the starter-kit placeholder until that's built.
    Route::get('dashboard', function () {
        return auth()->user()->isCandidate()
            ? redirect()->route('candidate.dashboard')
            : view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
