<?php
use App\Models\User;
use App\Models\JobListing;
use App\Models\EmployerProfile;
use App\Models\Category;
use App\Models\Skill;

function validJobListingPayload(): array
{
    $category = Category::firstOrCreate(['slug' => 'engineering'], ['name' => 'Engineering']);
    $skill = Skill::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']);

    return [
        'title' => 'Software Engineer',
        'description' => 'We are looking for a Software Engineer.',
        'location' => 'Remote',
        'employment_type' => 'full-time',
        'work_location' => 'remote',
        'categories' => [$category->id],
        'skills' => [
            $skill->id => ['selected' => '1', 'importance' => 'required'],
        ],
        'expires_at' => now()->addDays(10)->format('Y-m-d'),
    ];
}

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

test('guest can see job listings', function () {
    $response = $this->get(route('job-listings.index'));

    $response->assertStatus(200);
});

test('Employer can create job listing', function () {
    $employer = userWithRole('employer');
    EmployerProfile::factory()->for($employer)->create();

    $response = $this->actingAs($employer)->post(route('job-listings.store'), validJobListingPayload());

    $response->assertRedirect(route('job-listings.index'));
    $this->assertDatabaseHas('job_listings', [
        'title' => 'Software Engineer',
        'employment_type' => 'full-time',
        'work_location' => 'remote',
    ]);
});

test('Non-employer can\'t create job listing', function () {
    $candidate = userWithRole('candidate');

    $response = $this->actingAs($candidate)->post(route('job-listings.store'), validJobListingPayload());

    $response->assertStatus(403);
});

test('Employer can edit job listing, but only their own', function () {
    $employer = userWithRole('employer');
    $jobListing = JobListing::factory()->create(['user_id' => $employer->id]);

    // Employer can edit their own job listing
    $response = $this->actingAs($employer)->put(
        route('job-listings.update', $jobListing),
        ['title' => 'Updated Software Engineer'] + validJobListingPayload()
    );

    $response->assertRedirect(route('job-listings.show', $jobListing));
    $this->assertDatabaseHas('job_listings', [
        'id' => $jobListing->id,
        'title' => 'Updated Software Engineer',
    ]);

    // Employer cannot edit someone else's job listing
    $otherJobListing = JobListing::factory()
    ->for(userWithRole('employer'))
    ->create();

    $response = $this->actingAs($employer)->put(
        route('job-listings.update', $otherJobListing),
        ['title' => 'Updated Software Engineer'] + validJobListingPayload()
    );

    $response->assertStatus(403);
});

test('Employer can delete job listing, but only their own', function () {
    $employer = userWithRole('employer');
    $jobListing = JobListing::factory()->create(['user_id' => $employer->id]);

    // Employer can delete their own job listing
    $response = $this->actingAs($employer)->delete(route('job-listings.destroy', $jobListing));

    $response->assertRedirect(route('job-listings.index'));
    $this->assertDatabaseMissing('job_listings', [
        'id' => $jobListing->id,
    ]);

    // Employer cannot delete someone else's job listing
    $otherJobListing = JobListing::factory()
    ->for(userWithRole('employer'))
    ->create();

    $response = $this->actingAs($employer)->delete(route('job-listings.destroy', $otherJobListing));

    $response->assertStatus(403);
});

test('job listing can\'t be created without required fields', function () {
    $employer = userWithRole('employer');
    EmployerProfile::factory()->for($employer)->create();

    $response = $this->actingAs($employer)->post(route('job-listings.store'), [
        'title' => '',
        'description' => '',
        'location' => '',
        'employment_type' => '',
        'work_location' => '',
        'categories' => [],
        'skills' => [],
        'expires_at' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'description', 'location', 'employment_type', 'work_location']);
});
