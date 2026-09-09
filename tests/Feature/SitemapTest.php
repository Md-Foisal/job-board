<?php

use App\Models\Category;
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
