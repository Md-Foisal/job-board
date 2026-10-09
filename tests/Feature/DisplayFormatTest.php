<?php

use App\Enums\SalaryPeriod;
use App\Models\JobPosting;
use App\Support\DateFormat;
use App\Support\Money;
use Carbon\CarbonImmutable;

test('money takes the currency\'s own symbol where English has one, and the code where it does not', function (string $currency, string $expected) {
    expect(Money::format($currency, 72000))->toBe($expected);
})->with([
    'pound' => ['GBP', '£72,000'],
    'euro' => ['EUR', '€72,000'],
    'US dollar, never a bare $' => ['USD', 'US$72,000'],
    'Canadian dollar' => ['CAD', 'CA$72,000'],
    'taka' => ['BDT', "BDT\u{a0}72,000"],
    'code in lower case' => ['gbp', '£72,000'],
]);

test('money with no currency is just the figure', function () {
    expect(Money::format(null, 72000))->toBe('72,000');
});

test('a pay range names the currency on both ends', function () {
    expect(Money::range('GBP', 72000, 90000))->toBe('£72,000–£90,000')
        ->and(Money::range('GBP', null, null))->toBeNull();
});

test('a job card and a job page give the period in words', function () {
    $job = JobPosting::factory()->create([
        'salary_negotiable' => false,
        'salary_min' => 72000,
        'salary_max' => 90000,
        'salary_currency' => 'GBP',
        'salary_period' => SalaryPeriod::Yearly,
    ]);

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('£72,000–£90,000')
        ->assertSee('a year')
        ->assertDontSee('/ yearly');
});

test('each kind of date has one written form', function () {
    $moment = CarbonImmutable::parse('2026-11-05 14:27:00');

    expect($moment->format(DateFormat::DAY))->toBe('5 Nov 2026')
        ->and($moment->format(DateFormat::MONTH))->toBe('Nov 2026')
        ->and($moment->format(DateFormat::MOMENT))->toBe('5 Nov 2026, 2:27 pm')
        ->and($moment->format(DateFormat::DAY_SHORT))->toBe('5 Nov');
});
