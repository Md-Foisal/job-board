<?php

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;

test('sitemap lists active job postings and categories as XML', function () {
    $job = JobPosting::factory()->create(['title' => 'Product Designer']);
    $draftJob = JobPosting::factory()->draft()->create();
    $category = Category::create(['name' => 'Design', 'slug' => 'design']);

    $response = $this->get(route('sitemap'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('jobs.show', $job), false);
    $response->assertSee(route('categories.show', $category), false);
    $response->assertDontSee(route('jobs.show', $draftJob), false);
});

test('sitemap lists active company profiles but not suspended ones', function () {
    $active = Company::factory()->create();
    $suspended = Company::factory()->create(['account_status' => AccountStatus::Suspended]);

    $response = $this->get(route('sitemap'));

    $response->assertOk();
    $response->assertSee(route('companies.show', $active), false);
    $response->assertDontSee(route('companies.show', $suspended), false);
});

test('sitemap lists the static pages', function () {
    $response = $this->get(route('sitemap'));

    $response->assertOk();
    $response->assertSee(route('about'), false);
    $response->assertSee(route('privacy'), false);
    $response->assertSee(route('terms'), false);
});
