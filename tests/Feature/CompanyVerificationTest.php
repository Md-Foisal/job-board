<?php

use App\Enums\AccountStatus;
use App\Enums\DomainCheck;
use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\ModerationEvent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function companyRunBy(string $email, ?string $website = 'https://www.acme.com'): Company
{
    $company = Company::factory()->create(['website_url' => $website]);
    $owner = User::factory()->create(['email' => $email]);
    Membership::factory()->for($owner)->for($company)->create(['role' => MembershipRole::Owner]);

    return $company;
}

it('reads the email check the way a reviewer would', function (string $email, ?string $website, DomainCheck $expected) {
    expect(companyRunBy($email, $website)->domainCheck())->toBe($expected);
})->with([
    'same domain' => ['nadia@acme.com', 'https://www.acme.com', DomainCheck::Match],
    'a subdomain of it' => ['hr@jobs.acme.com', 'acme.com', DomainCheck::Match],
    'a look-alike domain' => ['boss@acme.com.evil.io', 'https://acme.com', DomainCheck::Mismatch],
    'a free mailbox' => ['rafiq@gmail.com', 'https://acme.com', DomainCheck::PersonalEmail],
    'no website at all' => ['rafiq@gmail.com', null, DomainCheck::NoWebsite],
]);

it('keeps company verification closed to anyone who is not staff', function () {
    $this->actingAs(candidateUser())
        ->get('/admin/companies')
        ->assertForbidden();
});

it('verifies a company and records who did it', function () {
    $company = companyRunBy('nadia@acme.com');
    $staff = staffWithTwoFactor();
    $this->actingAs($staff);

    Livewire::test(ManageCompanies::class)
        ->callAction(TestAction::make('verify')->table($company));

    $event = ModerationEvent::sole();

    expect($company->fresh()->verified_at)->not->toBeNull()
        ->and($event->action)->toBe(ModerationAction::VerifyCompany)
        ->and($event->admin_id)->toBe($staff->id);
});

it('records a request for documents without verifying anything', function () {
    $company = companyRunBy('rafiq@gmail.com', null);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageCompanies::class)
        ->callAction(TestAction::make('requestDocuments')->table($company), data: ['reason' => 'A trade licence, please.']);

    expect($company->fresh()->verified_at)->toBeNull()
        ->and(ModerationEvent::sole()->reason)->toBe('A trade licence, please.');
});

it('asks for the password before banning, then takes the company off the public site', function () {
    $company = companyRunBy('boss@acme.com');
    $posting = JobPosting::factory()->for($company)->create();
    $report = $company->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Scam']);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageCompanies::class)
        ->callAction(TestAction::make('ban')->table($company), data: ['reason' => 'Collecting fees from applicants.'])
        ->assertHasActionErrors(['current_password' => 'required']);

    expect($company->fresh()->account_status)->toBe(AccountStatus::Active);

    Livewire::test(ManageCompanies::class)
        ->callAction(TestAction::make('ban')->table($company), data: [
            'reason' => 'Collecting fees from applicants.',
            'current_password' => 'password',
        ]);

    expect($company->fresh()->account_status)->toBe(AccountStatus::Suspended)
        ->and(JobPosting::query()->active()->whereKey($posting->id)->exists())->toBeFalse()
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Actioned);
});

it('brings a banned company\'s postings back when the ban is lifted', function () {
    $company = companyRunBy('boss@acme.com');
    $company->account_status = AccountStatus::Suspended;
    $company->save();
    $posting = JobPosting::factory()->for($company)->create();
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageCompanies::class)
        ->set('activeTab', 'banned')
        ->callAction(TestAction::make('unban')->table($company));

    expect(JobPosting::query()->active()->whereKey($posting->id)->exists())->toBeTrue();
});

it('hides every decision about a company from staff who work there', function () {
    $company = companyRunBy('nadia@acme.com');
    $staff = staffWithTwoFactor();
    Membership::factory()->for($staff)->for($company)->create(['role' => MembershipRole::Member]);
    $this->actingAs($staff->fresh());

    Livewire::test(ManageCompanies::class)
        ->assertActionHidden(TestAction::make('verify')->table($company))
        ->assertActionHidden(TestAction::make('ban')->table($company));
});

it('bans a reported company from the reports queue with the same password check', function () {
    $company = companyRunBy('boss@acme.com');
    $report = $company->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Fake company']);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->callAction(TestAction::make('banCompany')->table($report), data: [
            'reason' => 'Not a real business.',
            'current_password' => 'password',
        ]);

    expect($company->fresh()->account_status)->toBe(AccountStatus::Suspended)
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Actioned);
});
