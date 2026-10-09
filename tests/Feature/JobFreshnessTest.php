<?php

use App\Models\Company;
use App\Models\JobPosting;
use Livewire\Livewire;

test('a posting says when it went up, on its page and on its card', function () {
    $posting = JobPosting::factory()->create(['title' => 'Data Analyst', 'published_at' => now()->subDays(3)]);

    $this->get(route('jobs.show', $posting))
        ->assertOk()
        ->assertSee('Posted')
        ->assertSee('3 days ago');

    Livewire::test('pages::job-search')->assertSee('3 days ago');
});

test('the closing badge shows only in a posting\'s last three days', function () {
    $soon = JobPosting::factory()->create(['expires_at' => now()->addDays(2)]);
    $later = JobPosting::factory()->create(['expires_at' => now()->addDays(20)]);

    $this->get(route('jobs.show', $soon))->assertSee('Closes in');
    $this->get(route('jobs.show', $later))->assertDontSee('Closes in');
});

test('newest means the day a posting went out, not the day its draft was begun', function () {
    $company = Company::factory()->create();

    // Begun as a draft three weeks ago and published an hour ago: it is
    // the newer posting, though its row is older and its id lower.
    $justOut = JobPosting::factory()->for($company)->create(['title' => 'Just Published Role', 'published_at' => now()->subHour()]);
    $justOut->forceFill(['created_at' => now()->subDays(21)])->saveQuietly();

    $lastWeek = JobPosting::factory()->for($company)->create(['title' => 'Last Week Role', 'published_at' => now()->subDays(7)]);
    $lastWeek->forceFill(['created_at' => now()->subDays(7)])->saveQuietly();

    $order = ['Just Published Role', 'Last Week Role'];

    Livewire::test('pages::job-search')->assertSeeInOrder($order);
    $this->get(route('home'))->assertSeeInOrder($order);
    $this->get(route('companies.show', $company))->assertSeeInOrder($order);
});
