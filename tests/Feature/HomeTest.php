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

test('lists every category with an open job, and none without one', function () {
    $job = JobPosting::factory()->create();
    $names = ['Accounts', 'Biology', 'Catering', 'Dentistry', 'Energy', 'Fashion', 'Gardening', 'Hospitality', 'Insurance', 'Journalism'];

    foreach ($names as $name) {
        $job->categories()->attach(Category::create(['name' => $name, 'slug' => strtolower($name)]));
    }

    Category::create(['name' => 'Unused Field', 'slug' => 'unused-field']);
    JobPosting::factory()->draft()->create()->categories()->attach(Category::create(['name' => 'Draft-only Field', 'slug' => 'draft-only-field']));

    $response = $this->get(route('home'))->assertOk();

    foreach ($names as $name) {
        $response->assertSee($name);
    }

    $response->assertDontSee('Unused Field')->assertDontSee('Draft-only Field');
});
