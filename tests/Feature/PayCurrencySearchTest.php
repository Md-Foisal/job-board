<?php

use App\Enums\SalaryPeriod;
use App\Models\Category;
use App\Models\JobPosting;
use App\Support\JobSearchCriteria;
use Livewire\Livewire;

function paidJob(string $title, ?string $currency, ?int $min, ?int $max): JobPosting
{
    return JobPosting::factory()->create([
        'title' => $title,
        'salary_negotiable' => $currency === null,
        'salary_currency' => $currency,
        'salary_min' => $min,
        'salary_max' => $max,
        'salary_period' => $currency === null ? null : SalaryPeriod::Monthly,
    ]);
}

test('a pay figure is kept only with a real currency', function () {
    expect(JobSearchCriteria::from(['salaryMin' => '50000']))->toBe([])
        ->and(JobSearchCriteria::from(['currency' => 'ABC', 'salaryMin' => '50000']))->toBe([])
        ->and(JobSearchCriteria::from(['currency' => 'RUR', 'salaryMin' => '50000']))->toBe([])
        ->and(JobSearchCriteria::from(['currency' => ' bdt ', 'salaryMin' => '50000']))
        ->toBe(['currency' => 'BDT', 'salaryMin' => 50000]);
});

test('a pay range matches only postings in that currency', function () {
    $taka = paidJob('Taka Role', 'BDT', 60000, 90000);
    paidJob('Yen Role', 'JPY', 300000, 400000);
    paidJob('Low Taka Role', 'BDT', 20000, 30000);
    paidJob('Negotiable Role', null, null, null);

    $criteria = JobSearchCriteria::from(['currency' => 'BDT', 'salaryMin' => 50000]);

    expect(JobPosting::query()->active()->matching($criteria)->pluck('id')->all())->toBe([$taka->id]);
});

test('a currency on its own shows the postings paid in it', function () {
    $euro = paidJob('Euro Role', 'EUR', 3000, 4000);
    paidJob('Dollar Role', 'USD', 3000, 4000);
    paidJob('Negotiable Role', null, null, null);

    expect(JobPosting::query()->active()->matching(JobSearchCriteria::from(['currency' => 'EUR']))->pluck('id')->all())
        ->toBe([$euro->id]);
});

test('pay sorts within the chosen currency, unknown pay and other currencies after', function () {
    $other = paidJob('Yen Role', 'JPY', 900000, 990000);
    $negotiable = paidJob('Negotiable Role', null, null, null);
    $low = paidJob('Low Role', 'BDT', 20000, 30000);
    $floorOnly = paidJob('Floor Role', 'BDT', 70000, null);
    $high = paidJob('High Role', 'BDT', 60000, 120000);

    expect(JobPosting::query()->sortBy('salary_high', 'BDT')->pluck('id')->take(3)->all())
        ->toBe([$high->id, $floorOnly->id, $low->id])
        ->and(JobPosting::query()->sortBy('salary_low', 'BDT')->pluck('id')->take(3)->all())
        ->toBe([$low->id, $high->id, $floorOnly->id])
        // The rest, newest first.
        ->and(JobPosting::query()->sortBy('salary_high', 'BDT')->pluck('id')->slice(3)->values()->all())
        ->toBe([$negotiable->id, $other->id]);
});

test('a pay sort without a currency is newest first', function () {
    $older = paidJob('Rich Role', 'BDT', 900000, 990000);
    $newer = paidJob('Modest Role', 'BDT', 20000, 30000);

    expect(JobPosting::query()->sortBy('salary_high')->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('the search lists only currencies open postings pay in', function () {
    paidJob('Taka Role', 'BDT', 50000, 80000);
    paidJob('Euro Role', 'EUR', 3000, 4000);

    Livewire::test('pages::job-search')
        ->assertSee('BDT — Bangladeshi Taka')
        ->assertSee('EUR — Euro')
        ->assertDontSee('JPY — Japanese Yen');
});

test('choosing a currency filters the search and offers the pay sorts in it', function () {
    paidJob('Taka Role', 'BDT', 50000, 80000);
    paidJob('Euro Role', 'EUR', 3000, 4000);

    Livewire::test('pages::job-search')
        ->assertDontSee('Pay in BDT: high to low')
        ->set('currency', 'BDT')
        ->assertSee('Taka Role')
        ->assertDontSee('Euro Role')
        ->assertSee('Pay in BDT: high to low')
        ->set('sort', 'salary_high')
        ->set('currency', '')
        ->assertSet('sort', 'newest')
        ->assertSee('Euro Role');
});

test('the pay amounts are asked for once a currency is chosen, and only apply with it', function () {
    paidJob('Taka Role', 'BDT', 50000, 80000);

    Livewire::test('pages::job-search')
        ->assertDontSee('Min / month')
        ->set('salaryMin', 900000)
        ->assertSee('Taka Role')
        ->set('currency', 'BDT')
        ->assertSee('Min / month')
        ->assertDontSee('Taka Role');
});

test('an address asking for a pay sort with no currency opens newest first', function () {
    Livewire::withQueryParams(['sort' => 'salary_high'])
        ->test('pages::job-search')
        ->assertSet('sort', 'newest');
});

test('the category page filters and sorts by pay in one currency too', function () {
    $category = Category::create(['name' => 'Backend', 'slug' => 'backend']);
    $taka = paidJob('Taka Role', 'BDT', 50000, 80000);
    $euro = paidJob('Euro Role', 'EUR', 3000, 4000);
    $category->jobPostings()->attach([$taka->id, $euro->id]);

    Livewire::test('pages::category-show', ['categoryModel' => $category])
        ->set('currency', 'EUR')
        ->assertSee('Euro Role')
        ->assertDontSee('Taka Role')
        ->assertSee('Pay in EUR: high to low');
});

test('an alert with pay needs a currency, and says it in words', function () {
    $user = candidateUser();

    Livewire::actingAs($user)
        ->test('pages::candidate.job-alerts')
        ->call('create')
        ->set('name', 'Taka jobs')
        ->set('salaryMin', 50000)
        ->call('save')
        ->assertHasErrors(['currency' => 'required_with'])
        ->set('currency', 'BDT')
        ->call('save')
        ->assertHasNoErrors();

    $criteria = $user->jobAlerts()->sole()->criteria;

    expect($criteria)->toBe(['currency' => 'BDT', 'salaryMin' => 50000])
        ->and(JobSearchCriteria::describe($criteria))->toBe(["pay from BDT\u{a0}50,000 a month"])
        ->and(JobSearchCriteria::describe(['currency' => 'EUR']))->toBe(['paid in EUR']);
});
