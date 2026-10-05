<?php

use App\Enums\MembershipRole;
use App\Models\CandidatePreference;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\SalaryCurrencies;
use Livewire\Livewire;

function currencyForm(Company $company, ?JobPosting $jobPosting = null)
{
    return Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-form', array_filter(['company' => $company, 'jobPosting' => $jobPosting]));
}

test('pay can be stated in every currency in use today, and in nothing else', function () {
    $codes = SalaryCurrencies::codes();

    expect($codes)->toContain('BDT', 'USD', 'EUR', 'JPY', 'XOF', 'XCG', 'ZWG')
        // Withdrawn (Bulgaria joined the euro in 2026, the Antillean guilder
        // ended in 2025), fund codes, metals, and the testing codes.
        ->not->toContain('RUR', 'XEU', 'AOR', 'HRK', 'BGN', 'ANG', 'ZWL', 'USN', 'CLF', 'XAU', 'XDR', 'XTS', 'XXX')
        ->and($codes)->toBe(collect($codes)->sort()->values()->all());
});

test('the job form offers the currencies as a list', function () {
    currencyForm(Company::factory()->create())
        ->assertSee('BDT — Bangladeshi Taka')
        ->assertDontSee('value="RUR"', false);
});

test('a pay figure cannot be published without a currency', function () {
    $company = Company::factory()->create();

    fillJobForm(currencyForm($company), ['salaryMin' => '50000', 'salaryMax' => '80000'])
        ->call('saveAndPublish')
        ->assertHasErrors(['salaryCurrency' => 'required_with']);
});

test('a draft can be parked before the currency is chosen', function () {
    $company = Company::factory()->create();

    fillJobForm(currencyForm($company), ['salaryMin' => '50000'])
        ->call('save')
        ->assertHasNoErrors();

    expect($company->jobPostings()->sole()->salary_min)->toBe(50000);
});

test('a code that is not a currency in use today is refused, and the form says why', function (string $code) {
    $company = Company::factory()->create();

    fillJobForm(currencyForm($company), ['salaryMin' => '50000', 'salaryCurrency' => $code])
        ->call('saveAndPublish')
        ->assertHasErrors('salaryCurrency')
        ->assertSee(e($code.' is not a currency in use today.'), false);
})->with(['withdrawn' => 'HRK', 'lower case' => 'usd', 'made up' => 'ABC']);

test('the chosen currency is saved with the pay', function () {
    $company = Company::factory()->create();

    fillJobForm(currencyForm($company), ['salaryMin' => '3000', 'salaryMax' => '4000', 'salaryCurrency' => 'USD'])
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    $job = $company->jobPostings()->sole();

    expect($job->salary_currency)->toBe('USD')
        ->and($job->salary_period->value)->toBe('monthly')
        ->and($job->salary_max_monthly)->toBe(4000);
});

test('negotiable leaves out whatever the hidden pay fields still hold', function () {
    $company = Company::factory()->create();

    fillJobForm(currencyForm($company), [
        'salaryMin' => '90000',
        'salaryMax' => '40000',
        'salaryCurrency' => 'HRK',
        'salaryNegotiable' => true,
    ])->call('saveAndPublish')->assertHasNoErrors();

    $job = $company->jobPostings()->sole();

    expect($job->salary_negotiable)->toBeTrue()
        ->and($job->salary_min)->toBeNull()
        ->and($job->salary_max)->toBeNull()
        ->and($job->salary_currency)->toBeNull()
        ->and($job->salary_period)->toBeNull();
});

test('a new posting starts in the currency the company last used', function () {
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->create(['salary_currency' => 'EUR']);
    JobPosting::factory()->create(['salary_currency' => 'JPY']);

    currencyForm($company)->assertSet('salaryCurrency', 'EUR');
});

test('a first posting, or a last one in a withdrawn currency, starts with none', function () {
    $company = Company::factory()->create();

    currencyForm($company)->assertSet('salaryCurrency', null);

    JobPosting::factory()->for($company)->create(['salary_currency' => 'HRK']);

    currencyForm($company)->assertSet('salaryCurrency', null);
});

test('a posting in a withdrawn currency opens showing it, marked', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create([
        'salary_negotiable' => false,
        'salary_min' => 9000,
        'salary_currency' => 'HRK',
    ]);

    currencyForm($company, $job)
        ->assertSet('salaryCurrency', 'HRK')
        ->assertSee('HRK — Croatian Kuna (no longer in use)');
});

test('a negotiable posting reopens on monthly, the period its select shows', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create([
        'salary_negotiable' => true,
        'salary_min' => null,
        'salary_max' => null,
        'salary_currency' => null,
        'salary_period' => null,
    ]);

    currencyForm($company, $job)->assertSet('salaryPeriod', 'monthly');
});

test('an empty number field is saved as nothing, not as an empty string', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create([
        'salary_negotiable' => false,
        'salary_min' => null,
        'salary_max' => null,
        'salary_currency' => null,
        'salary_period' => null,
        'min_experience_years' => null,
    ]);

    currencyForm($company, $job)->call('saveAndPublish')->assertHasNoErrors();

    $job->refresh();

    expect($job->getRawOriginal('salary_min'))->toBeNull()
        ->and($job->getRawOriginal('salary_max'))->toBeNull()
        ->and($job->getRawOriginal('min_experience_years'))->toBeNull();
});

test('a preference is saved with a currency from the list', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), [
            'desired_salary_min' => 3000,
            'desired_salary_currency' => 'EUR',
        ])
        ->assertSessionHasNoErrors();

    expect($user->candidateProfile->preference()->first()->desired_salary_currency)->toBe('EUR');
});

test('a preference refuses a code that is not a currency in use today', function (string $code) {
    $this->actingAs(candidateUser())
        ->patch(route('candidate.preferences.update'), [
            'desired_salary_min' => 3000,
            'desired_salary_currency' => $code,
        ])
        ->assertSessionHasErrors(['desired_salary_currency' => $code.' is not a currency in use today. Choose one from the list.']);
})->with(['withdrawn' => 'RUR', 'lower case' => 'usd']);

test('a desired salary needs a currency, and an empty preference needs none', function () {
    $this->actingAs(candidateUser())
        ->patch(route('candidate.preferences.update'), ['desired_salary_min' => 3000])
        ->assertSessionHasErrors('desired_salary_currency');

    $this->actingAs(candidateUser())
        ->patch(route('candidate.preferences.update'), ['desired_salary_currency' => ''])
        ->assertSessionHasNoErrors();
});

test('the preference form lists the currencies and keeps the saved one', function () {
    $user = candidateUser();
    CandidatePreference::factory()->for($user->candidateProfile)->create(['desired_salary_currency' => 'EUR']);

    $this->actingAs($user)
        ->get(route('candidate.preferences.edit'))
        ->assertOk()
        ->assertSee('EUR — Euro')
        ->assertSee('A job that pays in another currency is not compared with your salary.');
});

test('the pay reads as a range, or as one open end', function (array $pay, string $expected) {
    $job = JobPosting::factory()->make(['salary_negotiable' => false, 'salary_currency' => 'BDT', ...$pay]);

    expect($job->payRange())->toBe($expected);
})->with([
    'both ends' => [['salary_min' => 50000, 'salary_max' => 80000], 'BDT 50,000–80,000'],
    'one figure' => [['salary_min' => 50000, 'salary_max' => 50000], 'BDT 50,000'],
    'only a floor' => [['salary_min' => 50000, 'salary_max' => null], 'From BDT 50,000'],
    'only a ceiling' => [['salary_min' => null, 'salary_max' => 80000], 'Up to BDT 80,000'],
]);

test('a job page with only a floor never shows a range to zero', function () {
    $job = JobPosting::factory()->create([
        'salary_negotiable' => false,
        'salary_min' => 50000,
        'salary_max' => null,
        'salary_currency' => 'BDT',
    ]);

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('From BDT 50,000')
        ->assertDontSee('50,000–0');
});

test('factories only pick currencies in use today', function () {
    $currencies = JobPosting::factory()->count(30)->make()->pluck('salary_currency')
        ->merge(CandidatePreference::factory()->count(30)->make()->pluck('desired_salary_currency'))
        ->filter();

    expect($currencies)->not->toBeEmpty()
        ->and($currencies->reject(fn (string $code) => SalaryCurrencies::isInUse($code)))->toBeEmpty();
});
