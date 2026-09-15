<?php

use App\Models\Category;
use App\Models\JobPosting;

test('category page only shows job postings in that category', function () {
    $engineering = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $marketing = Category::create(['name' => 'Marketing', 'slug' => 'marketing']);

    $engineeringJob = JobPosting::factory()->create(['title' => 'Platform Engineer']);
    $engineeringJob->categories()->attach($engineering);

    $marketingJob = JobPosting::factory()->create(['title' => 'Growth Marketer']);
    $marketingJob->categories()->attach($marketing);

    $response = $this->get(route('categories.show', $engineering));

    $response->assertOk();
    $response->assertSee('Platform Engineer');
    $response->assertDontSee('Growth Marketer');
});
