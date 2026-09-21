<?php

use App\Models\Category;
use App\Models\JobPosting;
use App\Support\PublicCache;
use Carbon\CarbonInterface;
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

test('cached models come back as models, relation included, through a store that refuses to unserialize objects', function () {
    expect(config('cache.serializable_classes'))->toBeFalse()
        ->and(config('cache.stores.array.serialize'))->toBeTrue();

    $posting = JobPosting::factory()->create(['title' => 'Round Trip Role']);

    PublicCache::models('round-trip', JobPosting::class, fn () => JobPosting::with('company')->whereKey($posting->id)->get(), ['company']);
    $again = PublicCache::models('round-trip', JobPosting::class, fn () => throw new RuntimeException('should be cached'), ['company']);

    expect($again->sole())->toBeInstanceOf(JobPosting::class)
        ->and($again->sole()->title)->toBe('Round Trip Role')
        ->and($again->sole()->company->is($posting->company))->toBeTrue()
        ->and($again->sole()->expires_at)->toBeInstanceOf(CarbonInterface::class);
});

test('only plain data can be put in the public cache directly', function () {
    PublicCache::remember('not-plain', fn () => ['posting' => JobPosting::factory()->create()]);
})->throws(LogicException::class, 'PublicCache stores plain data only');
