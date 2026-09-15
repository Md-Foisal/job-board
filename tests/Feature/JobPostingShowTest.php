<?php

use App\Models\JobPosting;

test('guest can view a publicly visible job posting', function () {
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
});

test('guest cannot view a draft job posting', function () {
    $job = JobPosting::factory()->draft()->create();

    $response = $this->get(route('jobs.show', $job));

    $response->assertForbidden();
});

test('guest cannot view a job posting pending moderation', function () {
    $job = JobPosting::factory()->pendingModeration()->create();

    $response = $this->get(route('jobs.show', $job));

    $response->assertForbidden();
});

test('a company member can preview their own draft job posting', function () {
    $job = JobPosting::factory()->draft()->create();
    $member = employerUser($job->company);

    $response = $this->actingAs($member)->get(route('jobs.show', $job));

    $response->assertOk();
});

test('a company member from a different company cannot preview a draft job posting', function () {
    $job = JobPosting::factory()->draft()->create();
    $outsider = employerUser();

    $response = $this->actingAs($outsider)->get(route('jobs.show', $job));

    $response->assertForbidden();
});

test('the breadcrumb marks the current page rather than linking it', function () {
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk();
    // Shared <x-breadcrumb> component: the crumb for the page you are
    // already on carries aria-current and is not a link.
    $response->assertSee('aria-label="Breadcrumb"', false);
    $response->assertSee('aria-current="page"', false);
    $response->assertSee(route('jobs.index'), false);
});
