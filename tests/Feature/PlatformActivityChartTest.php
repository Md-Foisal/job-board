<?php

use App\Filament\Widgets\PlatformActivityChart;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
    $this->actingAs(staffWithTwoFactor());
});

test('views are summed per day over every posting, with the busiest day in words', function () {
    [$one, $two] = JobPosting::factory()->count(2)->create();
    JobPostingDailyStat::create(['job_posting_id' => $one->id, 'date' => '2026-09-28', 'views' => 40]);
    JobPostingDailyStat::create(['job_posting_id' => $two->id, 'date' => '2026-09-28', 'views' => 56]);
    JobPostingDailyStat::create(['job_posting_id' => $one->id, 'date' => '2026-10-01', 'views' => 4]);
    // Before the 30 days: left out.
    JobPostingDailyStat::create(['job_posting_id' => $one->id, 'date' => '2026-09-01', 'views' => 500]);

    Livewire::test(PlatformActivityChart::class)
        ->assertSee('Activity, last 30 days')
        ->assertSee('Job views: 100 in the last 30 days, most on 28 Sep (96).')
        ->assertSee('Bots, staff and each company')
        ->assertSee('role="img"', false);
});

test('a day is one point, whether its rows came from the model or from a counted view', function () {
    [$one, $two] = JobPosting::factory()->count(2)->create();
    JobPostingDailyStat::create(['job_posting_id' => $one->id, 'date' => '2026-09-28', 'views' => 40]);
    JobPostingDailyStat::query()->insert(['job_posting_id' => $two->id, 'date' => '2026-09-28', 'views' => 2]);

    Livewire::test(PlatformActivityChart::class)
        ->assertSee('Job views: 42 in the last 30 days, most on 28 Sep (42).');
});

test('every day of the range has a point, oldest first, and an empty day is zero', function () {
    $posting = JobPosting::factory()->create();
    JobPostingDailyStat::create(['job_posting_id' => $posting->id, 'date' => '2026-10-01', 'views' => 7]);

    $component = Livewire::test(PlatformActivityChart::class);
    $data = invade($component->instance())->getData();

    expect($data['labels'])->toHaveCount(PlatformActivityChart::DAYS)
        ->and($data['labels'][0])->toBe('2 Sep')
        ->and(end($data['labels']))->toBe('1 Oct')
        ->and($data['datasets'][0]['data'][0])->toBe(0)
        ->and(end($data['datasets'][0]['data']))->toBe(7);
});

test('the filter shows applications or postings published instead, one series at a time', function () {
    $posting = JobPosting::factory()->create(['published_at' => '2026-09-30 09:00:00']);
    JobPosting::factory()->create(['published_at' => '2026-08-01 09:00:00']);
    Application::factory()->count(3)->for($posting)->create(['created_at' => '2026-09-30 15:00:00']);

    Livewire::test(PlatformActivityChart::class)
        ->set('filter', 'applications')
        ->assertSee('Applications: 3 in the last 30 days, most on 30 Sep (3).')
        ->assertDontSee('Bots, staff')
        ->set('filter', 'postings')
        ->assertSee('Postings published: 1 in the last 30 days, most on 30 Sep (1).');
});

test('an unknown filter value falls back to views', function () {
    Livewire::test(PlatformActivityChart::class)
        ->set('filter', 'revenue')
        ->assertSee('Job views: 0 in the last 30 days.');
});

test('the counts are kept for ten minutes, so the chart does not query on every render', function () {
    $posting = JobPosting::factory()->create();

    Livewire::test(PlatformActivityChart::class)->assertSee('Job views: 0 ');

    JobPostingDailyStat::create(['job_posting_id' => $posting->id, 'date' => '2026-10-01', 'views' => 9]);
    Livewire::test(PlatformActivityChart::class)->assertSee('Job views: 0 ');

    $this->travel(PlatformActivityChart::CACHE_SECONDS + 1)->seconds();
    Livewire::test(PlatformActivityChart::class)->assertSee('Job views: 9 ');
});

test('it sits on the staff dashboard', function () {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSeeLivewire(PlatformActivityChart::class);
});
