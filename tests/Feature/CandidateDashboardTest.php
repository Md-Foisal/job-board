<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\JobView;

test('guest is redirected to login', function () {
    $response = $this->get(route('candidate.dashboard'));

    $response->assertRedirect(route('login'));
});

test('employer cannot view the candidate dashboard', function () {
    $employer = employerUser();

    $response = $this->actingAs($employer)->get(route('candidate.dashboard'));

    $response->assertForbidden();
});

test('the generic /dashboard route sends a candidate straight to their own dashboard', function () {
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('dashboard'));

    $response->assertRedirect(route('candidate.dashboard'));
});

test('profile completion percent counts only the filled candidateProfile fields', function () {
    $candidate = candidateUser();
    // 2 of the 6 tracked fields filled (headline, bio) -> round(2/6*100) = 33%.
    $candidate->candidateProfile->update([
        'headline' => 'Backend Developer',
        'bio' => 'Building things.',
        'cover_photo_path' => null,
        'portfolio_url' => null,
        'github_url' => null,
        'linkedin_url' => null,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSee('33%');
});

test('active application count only counts active outcomes', function () {
    $candidate = candidateUser();

    Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create()->id,
        'outcome_status' => ApplicationOutcomeStatus::Active,
    ]);
    Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create()->id,
        'outcome_status' => ApplicationOutcomeStatus::Rejected,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSee('Active applications');
    $response->assertSeeInOrder(['Active applications', '1']);
});

test('recently viewed jobs are listed most-recent-first', function () {
    $candidate = candidateUser();
    $older = JobPosting::factory()->create(['title' => 'Older Viewed Role']);
    $newer = JobPosting::factory()->create(['title' => 'Newer Viewed Role']);

    JobView::create(['user_id' => $candidate->id, 'job_posting_id' => $older->id, 'viewed_at' => now()->subDay()]);
    JobView::create(['user_id' => $candidate->id, 'job_posting_id' => $newer->id, 'viewed_at' => now()]);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Newer Viewed Role', 'Older Viewed Role']);
});

test('shows an empty state when the candidate has not viewed any jobs', function () {
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSee("haven't viewed");
});
