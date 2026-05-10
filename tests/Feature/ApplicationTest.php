<?php
use App\Models\User;
use App\Models\Application;
use App\Models\JobListing;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('candidate can see their applications', function () {
    $candidate = User::factory()->create(['role' => 'candidate']);
    $application = Application::factory()->create(['user_id' => $candidate->id]);

    $response = $this->actingAs($candidate)->get(route('applications.index'));

    $response->assertStatus(200);
});

test('guest cannot see applications', function () {
    $response = $this->get(route('applications.index'));
    $response->assertRedirect(route('login'));
});

test('candidate can apply for a job listing', function () {
    $candidate = User::factory()->create(['role' => 'candidate']);
    $jobListing = JobListing::factory()->create();

    Storage::fake('public');
    $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

    $response = $this->actingAs($candidate)->post(route('applications.store'), [
        'job_listing_id' => $jobListing->id,
        'cover_letter' => 'I am very interested in this position.',
        'resume' => $file,
    ]);

    $response->assertRedirect(route('applications.index'));
    $this->assertDatabaseHas('applications', [
        'user_id' => $candidate->id,
        'job_listing_id' => $jobListing->id,
        'cover_letter' => 'I am very interested in this position.',
        'resume' => 'resumes/' . $file->hashName(),
    ]);
    Storage::disk('public')->assertExists('resumes/' . $file->hashName());
});

test('non-candidate cannot apply for a job listing', function () {
    $employer = User::factory()->create(['role' => 'employer']);
    $jobListing = JobListing::factory()->for(
        User::factory()->create(['role' => 'employer'])
    )->create();

    Storage::fake('public');
    $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
    
    $response = $this->actingAs($employer)->post(route('applications.store'), [
        'job_listing_id' => $jobListing->id,
        'cover_letter' => 'I am very interested in this position.',
        'resume' => $file,
    ]);

    $response->assertStatus(403);
});

test('candidate cannot apply for the same job listing twice', function () {
    $candidate = User::factory()->create(['role' => 'candidate']);
    $jobListing = JobListing::factory()
    ->for(User::factory()->create(['role' => 'employer']))
    ->create();

    Application::factory()->create([
        'user_id' => $candidate->id,
        'job_listing_id' => $jobListing->id,
    ]);

        Storage::fake('public');
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

    $response = $this->actingAs($candidate)->post(route('applications.store'), [
        'job_listing_id' => $jobListing->id,
        'cover_letter' => 'I am very interested in this position.',
        'resume' => $file,
    ]);

    $response->assertStatus(302);
});

test('application can\'t be created without required fields', function () {
    $candidate = User::factory()->create(['role' => 'candidate']);

    $response = $this->actingAs($candidate)->post(route('applications.store'), ['cover_letter'=>'', 'resume'=>'', 'status'=>'', 'job_listing_id'=>'']);

    $response->assertSessionHasErrors(['resume', 'job_listing_id']);
});