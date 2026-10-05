<?php

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use App\Services\EmployerResponsiveness;
use App\Services\JobPerformance;
use App\Support\ReviewSummary;
use Database\Seeders\Demo\Catalogue;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

test('a fresh seed can be looked at from every side', function () {
    Storage::fake('local');

    $this->seed();

    $superAdmin = User::query()->where('email', 'superadmin@jobboard.test')->sole();
    $moderator = User::query()->where('email', 'moderator@jobboard.test')->sole();
    $candidate = User::query()->where('email', 'candidate@jobboard.test')->sole();

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($moderator->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and($candidate->candidateProfile->skills)->not->toBeEmpty()
        ->and($candidate->candidateProfile->applications()->count())->toBe(3)
        ->and($candidate->savedJobs()->count())->toBe(2)
        ->and($candidate->account_status)->toBe(AccountStatus::Active);

    expect(JobPosting::query()->where('moderation_status', ModerationStatus::Pending)->count())->toBeGreaterThanOrEqual(5)
        ->and(JobPosting::query()->where('moderation_status', ModerationStatus::Rejected)->whereHas('latestRejection')->exists())->toBeTrue()
        ->and(JobPosting::query()->where('title', 'Backend Engineer (Go)')->sole()->isHiddenByReports())->toBeTrue()
        ->and(Report::query()->where('review_status', 'pending')->exists())->toBeTrue()
        ->and(Company::query()->get()->contains(fn (Company $company) => $company->isTrustedPoster()))->toBeTrue()
        ->and(Company::query()->where('account_status', AccountStatus::Suspended)->exists())->toBeTrue()
        ->and(User::query()->where('account_status', AccountStatus::Suspended)->exists())->toBeTrue()
        ->and(ModerationEvent::query()->count())->toBeGreaterThan(5);

    // What layer 5 added: alerts, every posting state, and both ends of
    // account deletion.
    $demoCompany = Company::query()->where('slug', DemoAccountsSeeder::DEMO_COMPANY_SLUG)->sole();
    $deleted = User::withTrashed()->where('email', 'deleted@jobboard.test')->sole();

    expect($candidate->jobAlerts()->count())->toBe(2)
        ->and($candidate->jobAlerts()->where('is_active', false)->count())->toBe(1)
        ->and($demoCompany->jobPostings()->pluck('availability_status')->map->value->unique()->sort()->values()->all())
        ->toBe(['active', 'closed', 'draft', 'expired'])
        ->and($deleted->isRestorable())->toBeTrue()
        ->and(User::withTrashed()->whereNotNull('anonymized_at')->exists())->toBeTrue()
        ->and(JobPosting::query()->active()->whereNull('published_at')->exists())->toBeFalse();

    // The analytics page has a history to draw for the demo company.
    $report = app(JobPerformance::class)->for($demoCompany, days: 90);

    expect($report->views)->toBeGreaterThan(0)
        ->and($report->applications)->toBeGreaterThanOrEqual(14)
        ->and($report->funnel['hired'])->toBe(1)
        ->and($report->rejectedUnseen)->toBe(2)
        ->and($report->firstResponseMedianHours)->not->toBeNull()
        ->and($report->viewsCoverRange())->toBeFalse();

    // Postings read like real ones: written descriptions with their own
    // categories and skills, pay in the company's currency, and dates in
    // the past rather than all at the moment of seeding.
    $live = JobPosting::query()->active()->with(['company', 'categories', 'skills'])->get();

    expect($live)->not->toBeEmpty()
        ->and($live->every(fn (JobPosting $posting) => str_contains($posting->description, '<h3>')))->toBeTrue()
        ->and($live->every(fn (JobPosting $posting) => $posting->categories->isNotEmpty() && $posting->skills->isNotEmpty()))->toBeTrue()
        ->and($live->every(fn (JobPosting $posting) => $posting->published_at->isPast()))->toBeTrue()
        ->and($live->pluck('published_at')->map->toDateString()->unique()->count())->toBeGreaterThan(5)
        ->and($live->pluck('salary_currency')->filter()->unique()->count())->toBeGreaterThan(3)
        ->and(Company::query()->where('website_url', 'like', '%.example')->count())->toBeGreaterThanOrEqual(10)
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();

    // People read like real ones too: CVs a demo account can open are real
    // PDFs, every profile tells one story, and nobody applied before a job
    // went up or all at the moment of seeding.
    $cv = $candidate->candidateProfile->documents()->where('document_type', DocumentType::Cv)->sole();
    $demoApplications = Application::query()
        ->whereRelation('jobPosting', 'company_id', $demoCompany->id)
        ->whereHas('candidateProfile.user', fn ($query) => $query->whereNull('anonymized_at'))
        ->with('resumeDocument')
        ->get();

    expect(Storage::disk('local')->get($cv->file_path))->toStartWith('%PDF')
        ->and($cv->original_filename)->toEndWith('-CV.pdf')
        ->and($demoApplications)->toHaveCount(14)
        ->and($demoApplications->every(fn (Application $application) => Storage::disk('local')->exists($application->resumeDocument->file_path)))->toBeTrue();

    $people = CandidateProfile::query()
        ->whereHas('user', fn ($query) => $query->whereNull('anonymized_at'))
        ->with('experienceRecords')
        ->get();
    $places = collect(Catalogue::people()['places'])->pluck('currency');

    expect($people->every(fn (CandidateProfile $profile) => $profile->experienceRecords->isNotEmpty()
        && str_starts_with((string) $profile->headline, $profile->experienceRecords->sortByDesc('start_date')->first()->job_title)))->toBeTrue()
        ->and(CandidatePreference::query()->pluck('desired_salary_currency')->unique()->diff($places))->toBeEmpty();

    $applications = Application::query()->with('jobPosting')->get();

    expect($applications->every(fn (Application $application) => $application->created_at->gte($application->jobPosting->published_at)))->toBeTrue()
        ->and($applications->filter(fn (Application $application) => $application->created_at->lt(now()->subDay()))->count())
        ->toBeGreaterThan(intdiv($applications->count(), 2));

    // Every company was set up by someone of its own place, whose zone it
    // took; and a closing date is the end of a day in the company's zone,
    // the past one of the expired demo posting included.
    $owners = Company::query()->with('memberships.user')->get()
        ->flatMap(fn (Company $company) => $company->memberships->map(fn ($membership) => [$membership->user, $company]));
    $expired = $demoCompany->jobPostings()->where('availability_status', 'expired')->sole();

    expect($owners->every(fn (array $pair) => $pair[0]->timezone === $pair[1]->timezone))->toBeTrue()
        ->and($owners->every(fn (array $pair) => str_starts_with($pair[0]->email, Str::slug($pair[0]->name, '.')) || str_ends_with($pair[0]->email, '@jobboard.test')))->toBeTrue()
        ->and($expired->expires_at->setTimezone($demoCompany->timezone)->format('H:i:s'))->toBe('23:59:59');

    // The company page has reviews with averages, the mark, and one review
    // waiting in the staff queue.
    expect(ReviewSummary::of($demoCompany)->hasAverages())->toBeTrue()
        ->and($demoCompany->reviews()->where('moderation_status', ModerationStatus::Pending)->count())->toBe(1)
        ->and($demoCompany->reviews()->where('response_status', ModerationStatus::Approved)->count())->toBe(1)
        ->and(app(EmployerResponsiveness::class)->percentFor($demoCompany))->not->toBeNull();
});

test('the demo two-factor secret gives codes that sign staff in', function () {
    Storage::fake('local');

    $this->seed(DemoAccountsSeeder::class);

    $moderator = User::query()->where('email', 'moderator@jobboard.test')->sole();
    $code = app(Google2FA::class)->getCurrentOtp(DemoAccountsSeeder::TWO_FACTOR_SECRET);

    expect(app(TwoFactorAuthenticationProvider::class)->verify(decrypt($moderator->two_factor_secret), $code))->toBeTrue();
});

test('known passwords are never seeded outside a developer machine', function () {
    app()->detectEnvironment(fn () => 'production');

    // Run directly: through db:seed, production's "are you sure?" prompt
    // would stop it first and the test would prove nothing.
    (new DemoAccountsSeeder)->run();

    expect(User::query()->where('email', 'superadmin@jobboard.test')->exists())->toBeFalse();
});
