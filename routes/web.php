<?php

use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\CandidateDashboardController;
use App\Http\Controllers\CandidatePreferenceController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateSavedJobController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\EmployerCompanyController;
use App\Http\Controllers\EmployerDashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\RecruiterProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Static guest pages (claude/14 route ১০). Route::view, not a
// controller: there is no data to fetch, and they are linked from
// Shell A's footer and listed in the sitemap.
Route::view('/about', 'static.about')->name('about');
Route::view('/privacy', 'static.privacy')->name('privacy');
Route::view('/terms', 'static.terms')->name('terms');

Route::livewire('/jobs', 'pages::job-search')->name('jobs.index');
Route::livewire('/categories/{categoryModel:slug}', 'pages::category-show')->name('categories.show');
Route::get('/jobs/{job_posting:slug}', [JobPostingController::class, 'show'])->name('jobs.show');
/*
 * Registered ahead of the public slug route below: '/companies/create'
 * would otherwise be read as a company whose slug is "create".
 */
Route::middleware('auth')->group(function () {
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
});

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

    // The application's own timeline (claude/14 route ২০) -- the
    // ghosting-killer page. withdraw is a PATCH on the same record
    // rather than its own resource: it flips one field on the
    // application and appends an ApplicationEvent.
    Route::get('/applications/{application}', [CandidateApplicationController::class, 'show'])->name('applications.show');
    Route::patch('/applications/{application}/withdraw', [CandidateApplicationController::class, 'withdraw'])->name('applications.withdraw');
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
        $user = auth()->user();

        if ($user->isCandidate()) {
            return redirect()->route('candidate.dashboard');
        }

        // Someone who works at a company has a real workspace to land in,
        // so skip the placeholder. Anyone else -- staff, or an account
        // with neither side set up yet -- still gets it; a fresh employer
        // registration is sent straight to company setup at the moment of
        // registering, where the intent is actually known.
        $company = $user->activeCompanies()->first();

        return $company
            ? redirect()->route('employer.dashboard', $company)
            : view('dashboard');
    })->name('dashboard');
});

/*
 * The company workspace (Shell C). The company is a URL segment rather
 * than a session value so these pages can be linked, bookmarked and kept
 * open side by side for two different companies; 'company.member' is what
 * turns that URL into an entitlement check on every single request.
 *
 * Each page inside adds its own route as it is built.
 */
/*
 * Deliberately outside the company prefix: a recruiter's public face
 * belongs to the person, not to any one company, and an agency recruiter
 * posting for three clients shows the same face to all of them.
 */
Route::middleware(['auth', 'employer'])->group(function () {
    Route::get('/employer/profile', [RecruiterProfileController::class, 'edit'])->name('employer.recruiter-profile.edit');
    Route::patch('/employer/profile', [RecruiterProfileController::class, 'update'])->name('employer.recruiter-profile.update');
});

Route::middleware(['auth', 'company.member'])
    ->prefix('companies/{company:slug}')
    ->name('employer.')
    ->group(function () {
        Route::get('/dashboard', [EmployerDashboardController::class, 'index'])->name('dashboard');

        Route::get('/edit', [EmployerCompanyController::class, 'edit'])->name('company.edit');
        Route::patch('/', [EmployerCompanyController::class, 'update'])->name('company.update');

        Route::livewire('/team', 'pages::employer.team')->name('team.index');
    });

/*
 * Reachable without signing in: the whole point of an invitation is that
 * it may arrive before the recipient has an account. Accepting still
 * requires being signed in as the invited address -- the controller
 * sends a guest through login and back.
 */
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

require __DIR__.'/settings.php';
