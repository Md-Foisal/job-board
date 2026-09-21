<?php

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Rows written in the same second tie on their timestamp, and a database
 * may return tied rows in any order -- different on every run, and across
 * pages of a paginated list. The id breaks the tie: newer wins.
 */
test('job search lists postings from the same second newest first, every time', function () {
    Carbon::setTestNow(now()->startOfSecond());
    $first = JobPosting::factory()->create(['title' => 'First Role']);
    $second = JobPosting::factory()->create(['title' => 'Second Role']);

    Livewire::test('pages::job-search')->assertSeeInOrder(['Second Role', 'First Role']);
});

test('a candidate sees applications sent in the same second newest first', function () {
    Carbon::setTestNow(now()->startOfSecond());
    $candidate = candidateUser();
    $older = Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create(['title' => 'Older Role'])->id,
    ]);
    $newer = Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create(['title' => 'Newer Role'])->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index'))
        ->assertSeeInOrder(['Newer Role', 'Older Role']);
});
