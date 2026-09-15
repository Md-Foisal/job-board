<?php

use App\Models\Category;
use App\Models\JobPosting;

test('shows active job postings', function () {
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
});

test('hides draft job postings', function () {
    JobPosting::factory()->draft()->create(['title' => 'Hidden Draft Role']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Hidden Draft Role');
});

test('hides job postings pending moderation', function () {
    JobPosting::factory()->pendingModeration()->create(['title' => 'Awaiting Approval Role']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Awaiting Approval Role');
});

test('hides expired job postings', function () {
    JobPosting::factory()->create([
        'title' => 'Expired Role',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Expired Role');
});

test('shows categories with their active job posting count', function () {
    $category = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $job = JobPosting::factory()->create();
    $job->categories()->attach($category);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Engineering');
});
