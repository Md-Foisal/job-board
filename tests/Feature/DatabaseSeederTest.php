<?php

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

test('a fresh seed can be looked at from every side', function () {
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
});

test('the demo two-factor secret gives codes that sign staff in', function () {
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
