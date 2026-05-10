<?php
use App\Models\User;
use App\Models\JobListing;

test('user can see job listings', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get(route('job-listings.index'));

    $response->assertStatus(200);
});

test('user can see job listing details', function () {
    $user = User::factory()->create();
    $jobListing = JobListing::factory()->create();

    $response = $this->actingAs($user)->get(route('job-listings.show', $jobListing));

    $response->assertStatus(200);
});

test('guest cannot see job listings', function () {
    $response = $this->get(route('job-listings.index'));

    $response->assertRedirect(route('login'));
})
->todo('It will redirect to the login page after creating guest layout');

test('Employer can create job listing', function () {
    $employer = User::factory()->create(['role' => 'employer']);

    $response = $this->actingAs($employer)->post(route('job-listings.store'), [
        'title' => 'Software Engineer',
        'company' => 'Tech Company',
        'description' => 'We are looking for a Software Engineer.',
        'location' => 'Remote',
        'salary' => '$100,000',
        'type' => 'full-time',
    ]);

    $response->assertRedirect(route('job-listings.index'));
    $this->assertDatabaseHas('job_listings', [
        'title' => 'Software Engineer',
        'company' => 'Tech Company',
    ]);
});

test('Non-employer can\'t create job listing', function () {
    $candidate = User::factory()->create(['role' => 'candidate']);

    $response = $this->actingAs($candidate)->post(route('job-listings.store'), [
        'title' => 'Software Engineer',
        'company' => 'Tech Company',
        'description' => 'We are looking for a Software Engineer.',
        'location' => 'Remote',
        'salary' => '$100,000',
        'type' => 'full-time',
    ]);

    $response->assertStatus(403);
});

test('Employer can edit job listing, but only their own', function () {
    $employer = User::factory()->create(['role' => 'employer']);
    $jobListing = JobListing::factory()->create(['user_id' => $employer->id]);

    // Employer can edit their own job listing
    $response = $this->actingAs($employer)->put(route('job-listings.update', $jobListing), [
        'title' => 'Updated Software Engineer',
        'company' => 'Tech Company',
        'description' => 'We are looking for a Software Engineer.',
        'location' => 'Remote',
        'salary' => '$100,000',
        'type' => 'full-time',
    ]);

    $response->assertRedirect(route('job-listings.show', $jobListing));
    $this->assertDatabaseHas('job_listings', [
        'id' => $jobListing->id,
        'title' => 'Updated Software Engineer',
    ]);

    // Employer cannot edit someone else's job listing
    $otherJobListing = JobListing::factory()
    ->for(User::factory()->create(['role' => 'employer']))
    ->create();

    $response = $this->actingAs($employer)->put(route('job-listings.update', $otherJobListing), [
        'title' => 'Updated Software Engineer',
        'company' => 'Tech Company',
        'description' => 'We are looking for a Software Engineer.',
        'location' => 'Remote',
        'salary' => '$100,000',
        'type' => 'full-time',
    ]);

    $response->assertStatus(403);
});

test('Employer can delete job listing, but only their own', function () {
    $employer = User::factory()->create(['role' => 'employer']);
    $jobListing = JobListing::factory()->create(['user_id' => $employer->id]);

    // Employer can delete their own job listing
    $response = $this->actingAs($employer)->delete(route('job-listings.destroy', $jobListing));

    $response->assertRedirect(route('job-listings.index'));
    $this->assertDatabaseMissing('job_listings', [
        'id' => $jobListing->id,
    ]);

    // Employer cannot delete someone else's job listing
    $otherJobListing = JobListing::factory()
    ->for(User::factory()->create(['role' => 'employer']))
    ->create();

    $response = $this->actingAs($employer)->delete(route('job-listings.destroy', $otherJobListing));

    $response->assertStatus(403);
});

test('job listing can\'t be created without required fields', function () {
    $employer = User::factory()->create(['role' => 'employer']);

    $response = $this->actingAs($employer)->post(route('job-listings.store'), [
        'title' => '',
        'company' => '',
        'description' => '',
        'location' => '',
        'salary' => '',
        'type' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'company', 'description', 'location', 'type']);
});