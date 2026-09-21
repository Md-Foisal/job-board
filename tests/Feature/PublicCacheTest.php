<?php

use App\Models\Category;
use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('the homepage is served from the cache, and a real change replaces it', function () {
    $posting = JobPosting::factory()->create(['title' => 'Original Title']);

    $this->get('/')->assertSee('Original Title');

    // A write that bypasses the model fires no events, so a cached page
    // keeps showing what it had: proof the second request did not query.
    DB::table('job_postings')->where('id', $posting->id)->update(['title' => 'Quiet Change']);
    $this->get('/')->assertSee('Original Title')->assertDontSee('Quiet Change');

    $posting->update(['title' => 'Proper Change']);
    $this->get('/')->assertSee('Proper Change');
});

test('a new posting reaches the sitemap without waiting for the cache to expire', function () {
    $this->get(route('sitemap'))->assertOk();

    $posting = JobPosting::factory()->create();

    $this->get(route('sitemap'))->assertSee(route('jobs.show', $posting));
});

test('renaming a category reaches the search filters straight away', function () {
    $category = Category::create(['name' => 'Backend', 'slug' => 'backend']);

    Livewire::test('pages::job-search')->assertSee('Backend');

    $category->update(['name' => 'Server Side']);

    Livewire::test('pages::job-search')->assertSee('Server Side')->assertDontSee('>Backend<', false);
});

test('a category that is removed leaves the filters with it', function () {
    $category = Category::create(['name' => 'Retired Field', 'slug' => 'retired-field']);

    Livewire::test('pages::job-search')->assertSee('Retired Field');

    $category->delete();

    Livewire::test('pages::job-search')->assertDontSee('Retired Field');
});
