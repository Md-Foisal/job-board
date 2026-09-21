<?php

use App\Enums\AvailabilityStatus;
use App\Models\JobPosting;

test('saved jobs that are no longer open can be removed in one go, leaving the open ones', function () {
    $candidate = candidateUser();
    $open = JobPosting::factory()->create(['title' => 'Still Open Role']);
    $expired = JobPosting::factory()->create(['expires_at' => now()->subDay()]);
    $closed = JobPosting::factory()->create(['availability_status' => AvailabilityStatus::Closed]);
    $candidate->savedJobs()->attach([$open->id, $expired->id, $closed->id]);

    $this->actingAs($candidate)
        ->get(route('candidate.saved-jobs.index'))
        ->assertSee('2 jobs you saved are no longer available')
        ->assertSee('Remove them');

    $this->actingAs($candidate)
        ->from(route('candidate.saved-jobs.index'))
        ->delete(route('candidate.saved-jobs.prune'))
        ->assertRedirect(route('candidate.saved-jobs.index'))
        ->assertSessionHas('success', 'Removed 2 saved jobs that are no longer open.');

    expect($candidate->savedJobs()->pluck('job_postings.id')->all())->toBe([$open->id]);
});

test('with only unavailable saved jobs, the page does not claim nothing was saved', function () {
    $candidate = candidateUser();
    $candidate->savedJobs()->attach(JobPosting::factory()->create(['expires_at' => now()->subDay()])->id);

    $this->actingAs($candidate)
        ->get(route('candidate.saved-jobs.index'))
        ->assertSee('None of the jobs you saved is open right now.')
        ->assertDontSee("You haven't saved any jobs yet.");
});

test('another candidate\'s saved jobs are untouched', function () {
    $mine = candidateUser();
    $theirs = candidateUser();
    $expired = JobPosting::factory()->create(['expires_at' => now()->subDay()]);
    $mine->savedJobs()->attach($expired->id);
    $theirs->savedJobs()->attach($expired->id);

    $this->actingAs($mine)->delete(route('candidate.saved-jobs.prune'));

    expect($theirs->savedJobs()->count())->toBe(1);
});
