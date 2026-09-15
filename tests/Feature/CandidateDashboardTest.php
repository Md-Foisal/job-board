<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\Skill;

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

test('profile completion percent counts the filled candidateProfile fields', function () {
    $candidate = candidateUser();
    // 2 of the 10 tracked items filled (headline, bio) -> round(2/10*100) = 20%.
    // The other 8 are the four empty scalar fields below plus education,
    // experience, skills and documents, which this candidate has none of.
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
    $response->assertSee('20%');
});

test('profile completion percent also counts education, experience, skills and documents', function () {
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update([
        'headline' => 'Backend Developer',
        'bio' => 'Building things.',
        'cover_photo_path' => null,
        'portfolio_url' => null,
        'github_url' => null,
        'linkedin_url' => null,
    ]);

    // Same two scalar fields as the test above, but all four sections filled:
    // 6 of 10 -> 60%. If the sections were not counted this would read 20%.
    EducationRecord::factory()->for($profile)->create();
    ExperienceRecord::factory()->for($profile)->create();
    Document::factory()->for($profile)->create(['document_type' => DocumentType::Cv]);
    $profile->skills()->attach(Skill::create(['name' => 'Laravel', 'slug' => 'laravel'])->id);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSee('60%');
});

test('a profile with every field and section filled reads 100 percent', function () {
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update([
        'headline' => 'Backend Developer',
        'bio' => 'Building things.',
        'cover_photo_path' => 'covers/candidate.jpg',
        'portfolio_url' => 'https://example.test',
        'github_url' => 'https://github.com/example',
        'linkedin_url' => 'https://linkedin.com/in/example',
    ]);

    EducationRecord::factory()->for($profile)->create();
    ExperienceRecord::factory()->for($profile)->create();
    Document::factory()->for($profile)->create(['document_type' => DocumentType::Cv]);
    $profile->skills()->attach(Skill::create(['name' => 'Laravel', 'slug' => 'laravel'])->id);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    $response->assertOk();
    $response->assertSee('100%');
});

test('the completion card names the missing items, not just the percentage', function () {
    $candidate = candidateUser();
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
    // 8 items are missing: the card names the first three and counts the rest.
    $response->assertSee('Cover photo');
    $response->assertSee('Portfolio link');
    $response->assertSee('GitHub link');
    $response->assertSee('+5 more');
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
