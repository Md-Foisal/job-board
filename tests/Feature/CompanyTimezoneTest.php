<?php

use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\User;
use App\Services\JobPerformance;
use App\Support\ClosingDate;
use App\Support\JobPerformanceReport;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * 20:00 UTC on 2 October is already 02:00 on 3 October in Dhaka, and
 * still 16:00 on 2 October in New York.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 20:00:00', 'UTC'));
    $this->dhaka = Company::factory()->create(['timezone' => 'Asia/Dhaka']);
});

function dhakaViews(JobPosting $job): array
{
    return JobPostingDailyStat::query()
        ->where('job_posting_id', $job->id)
        ->get()
        ->mapWithKeys(fn (JobPostingDailyStat $stat) => [$stat->date->toDateString() => $stat->views])
        ->all();
}

test('a new company takes its creator\'s zone', function () {
    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookie(LocalTime::COOKIE, 'Asia/Dhaka')
        ->post(route('companies.store'), ['name' => 'Northwind Logistics', 'identity_type' => 'company']);

    expect(Company::where('name', 'Northwind Logistics')->sole()->timezone)->toBe('Asia/Dhaka');
});

test('without any zone to go on a new company is on UTC', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('companies.store'), ['name' => 'Beacon Analytics', 'identity_type' => 'agency']);

    expect(Company::where('name', 'Beacon Analytics')->sole()->timezone)->toBe('UTC');
});

test('the owner can change the company\'s zone, and only to a real one', function () {
    $owner = employerUser($this->dhaka, MembershipRole::Owner);
    $fields = ['name' => $this->dhaka->name, 'identity_type' => 'company'];

    $this->actingAs($owner)
        ->patch(route('employer.company.update', $this->dhaka), [...$fields, 'timezone' => 'Mars/Olympus_Mons'])
        ->assertSessionHasErrors('timezone');

    $this->actingAs($owner)
        ->patch(route('employer.company.update', $this->dhaka), [...$fields, 'timezone' => 'Europe/London'])
        ->assertSessionHasNoErrors();

    expect($this->dhaka->fresh()->timezone)->toBe('Europe/London');

    $this->actingAs($owner)
        ->patch(route('employer.company.update', $this->dhaka), $fields)
        ->assertSessionHasNoErrors();

    expect($this->dhaka->fresh()->timezone)->toBe('Europe/London');
});

test('a closing date runs to the end of that day in the company\'s zone', function () {
    fillJobForm(
        Livewire::actingAs(employerUser($this->dhaka, MembershipRole::Owner))
            ->test('pages::employer.job-form', ['company' => $this->dhaka]),
        ['expiresAt' => '2026-10-30'],
    )->call('saveAndPublish')->assertHasNoErrors();

    $posting = $this->dhaka->jobPostings()->sole();

    expect($posting->expires_at->toDateTimeString())->toBe('2026-10-30 17:59:59')
        ->and(ClosingDate::day($posting)->toDateString())->toBe('2026-10-30');

    Livewire::actingAs(employerUser($this->dhaka, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $this->dhaka, 'jobPosting' => $posting])
        ->assertSet('expiresAt', '2026-10-30');
});

test('a posting still takes applications late on its closing day, and not a second after', function () {
    $posting = JobPosting::factory()->for($this->dhaka)->create([
        'expires_at' => ClosingDate::endOf('2026-10-30', $this->dhaka),
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-10-30 23:30:00', 'Asia/Dhaka'));
    expect($posting->fresh()->isExpired())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-10-31 00:00:00', 'Asia/Dhaka'));
    expect($posting->fresh()->isExpired())->toBeTrue();
});

test('"today" for the closing date is the company\'s today', function () {
    $form = fn (Company $company) => fillJobForm(
        Livewire::actingAs(employerUser($company, MembershipRole::Owner))
            ->test('pages::employer.job-form', ['company' => $company]),
        ['expiresAt' => '2026-10-03'],
    )->call('saveAndPublish');

    // Already 3 October in Dhaka, so that day cannot be the closing date.
    $form($this->dhaka)->assertHasErrors('expiresAt');
    $form(Company::factory()->create(['timezone' => 'America/New_York']))->assertHasNoErrors();
});

test('the form names the zone the closing date runs in', function () {
    Livewire::actingAs(employerUser($this->dhaka, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $this->dhaka])
        ->assertSee('Asia/Dhaka (GMT+06:00)')
        ->assertSet('expiresAt', '2026-11-03');
});

test('the listing shows the closing day the company picked', function () {
    JobPosting::factory()->for($this->dhaka)->create(['expires_at' => ClosingDate::endOf('2026-10-30', $this->dhaka)]);

    Livewire::actingAs(employerUser($this->dhaka, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $this->dhaka])
        ->assertSee('Closes Oct 30, 2026');
});

test('extending gives a month to the end of that day, without running past a short month', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-31 08:00:00', 'Asia/Dhaka'));
    $posting = JobPosting::factory()->for($this->dhaka)->create([
        'availability_status' => AvailabilityStatus::Expired,
        'expires_at' => now()->subDay(),
    ]);

    Livewire::actingAs(employerUser($this->dhaka, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $this->dhaka])
        ->call('extend', $posting->id);

    expect(ClosingDate::day($posting->fresh())->toDateTimeString())->toBe('2027-02-28 23:59:59');
});

test('a view counts on the company\'s day', function () {
    $posting = JobPosting::factory()->for($this->dhaka)->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15')
        ->get(route('jobs.show', $posting))
        ->assertOk();

    expect(dhakaViews($posting))->toBe(['2026-10-03' => 1]);
});

test('one session counts each posting once on its own company\'s day, even when those days differ', function () {
    $inDhaka = JobPosting::factory()->for($this->dhaka)->create();
    $inNewYork = JobPosting::factory()->for(Company::factory()->create(['timezone' => 'America/New_York']))->create();
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15');

    $this->get(route('jobs.show', $inDhaka));
    $this->get(route('jobs.show', $inNewYork));
    $this->get(route('jobs.show', $inDhaka));
    $this->get(route('jobs.show', $inNewYork));

    expect(dhakaViews($inDhaka))->toBe(['2026-10-03' => 1])
        ->and(dhakaViews($inNewYork))->toBe(['2026-10-02' => 1]);
});

test('analytics put an application on the company\'s day', function () {
    $posting = JobPosting::factory()->for($this->dhaka)->create();
    Application::factory()->for($posting)->create(['created_at' => now()]);

    $report = app(JobPerformance::class)->for($this->dhaka, $posting, 7);

    expect($report->dailyApplications)->toHaveKey('2026-10-03')
        ->and($report->dailyApplications['2026-10-03'])->toBe(1)
        ->and(array_key_last($report->dailyApplications))->toBe('2026-10-03')
        ->and(array_key_first($report->dailyApplications))->toBe('2026-09-27');
});

test('views counted from the first day of the range cover it, whatever the company\'s zone', function () {
    $posting = JobPosting::factory()->for($this->dhaka)->create();
    JobPostingDailyStat::create(['job_posting_id' => $posting->id, 'date' => '2026-09-27', 'views' => 4]);

    $report = app(JobPerformance::class)->for($this->dhaka, $posting, 7);

    expect($report)->toBeInstanceOf(JobPerformanceReport::class)
        ->and($report->viewsCoverRange())->toBeTrue()
        ->and($report->views)->toBe(4);
});

test('the analytics page says which zone its days are in', function () {
    JobPosting::factory()->for($this->dhaka)->create();

    $this->actingAs(employerUser($this->dhaka, MembershipRole::Owner))
        ->get(route('employer.analytics', $this->dhaka))
        ->assertOk()
        ->assertSee('Days run from midnight to midnight in your company&#039;s time zone, Asia/Dhaka (GMT+06:00).', false);
});
