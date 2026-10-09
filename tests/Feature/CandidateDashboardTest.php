<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\CompanyReview;
use App\Models\ExperienceRecord;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\Skill;
use App\Models\User;

function applicationOf(User $candidate, array $attributes = []): Application
{
    return Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        ...$attributes,
    ]);
}

test('guest is redirected to login', function () {
    $this->get(route('candidate.dashboard'))->assertRedirect(route('login'));
});

test('employer cannot view the candidate dashboard', function () {
    $this->actingAs(employerUser())->get(route('candidate.dashboard'))->assertForbidden();
});

test('the generic /dashboard route sends a candidate straight to their own dashboard', function () {
    $this->actingAs(candidateUser())->get(route('dashboard'))->assertRedirect(route('candidate.dashboard'));
});

test('the greeting uses the first name, or the whole name after a short form like Md.', function (string $name, string $greeting) {
    $candidate = candidateUser();
    $candidate->update(['name' => $name]);

    $this->actingAs($candidate)->get(route('candidate.dashboard'))->assertSee($greeting);
})->with([
    ['Rafi Ahmed', 'Hi, Rafi'],
    ['Md. Foisal', 'Hi, Md. Foisal'],
]);

test('applications are counted by where they stand, each count linking to its list', function () {
    $candidate = candidateUser();
    applicationOf($candidate);
    applicationOf($candidate, ['stage' => ApplicationStage::Shortlisted]);
    applicationOf($candidate, ['stage' => ApplicationStage::Interview]);
    applicationOf($candidate, ['stage' => ApplicationStage::Offer]);
    applicationOf($candidate, ['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);
    applicationOf($candidate, ['outcome_status' => ApplicationOutcomeStatus::Rejected, 'decided_at' => now()->subDay()]);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    expect($response->viewData('statusCounts'))->toBe(['applied' => 1, 'in-review' => 2, 'offer' => 1, 'closed' => 2]);
    $response->assertSee(route('candidate.applications.index', ['status' => 'in-review']), false);

    $inReview = $this->actingAs($candidate)->get(route('candidate.applications.index', ['status' => 'in-review']));
    expect($inReview->viewData('applications'))->toHaveCount(2);
    $inReview->assertSee('Show all');
});

test('the line under the greeting counts the applications the companies moved this week', function () {
    $candidate = candidateUser();
    $moved = applicationOf($candidate, ['stage' => ApplicationStage::Shortlisted]);
    $moved->events()->create(['changed_by_id' => User::factory()->create()->id, 'from_stage' => 'new', 'to_stage' => 'shortlisted']);

    $this->actingAs($candidate)->get(route('candidate.dashboard'))
        ->assertSee('One of your applications moved this week.');

    $this->travel(8)->days();

    $this->actingAs($candidate)->get(route('candidate.dashboard'))
        ->assertSee('No news from the companies this week.');
});

test('recent changes show the last three, newest first, naming the company and never its staff', function () {
    $candidate = candidateUser();
    $application = applicationOf($candidate);
    $company = $application->jobPosting->company->name;
    $manager = User::factory()->create(['name' => 'Hannah Lewis']);

    $this->travel(1)->hours();
    $application->events()->create(['changed_by_id' => $manager->id, 'from_stage' => 'new', 'to_stage' => 'shortlisted']);
    $this->travel(1)->hours();
    $application->events()->create(['changed_by_id' => $manager->id, 'from_stage' => 'shortlisted', 'to_stage' => 'interview']);
    $this->travel(1)->hours();
    $application->events()->create(['changed_by_id' => $candidate->id, 'from_outcome_status' => 'active', 'to_outcome_status' => 'withdrawn']);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    expect($response->viewData('recentChanges'))->toHaveCount(3);
    $response->assertSeeInOrder([
        'You withdrew this application',
        "{$company} moved your application to Interview",
        "{$company} moved your application to Shortlisted",
    ])->assertDontSee('Hannah Lewis');
});

test('a saved job closing within three days needs the candidate, unless they applied', function () {
    $candidate = candidateUser();
    $closing = JobPosting::factory()->create(['title' => 'Closing Soon Role', 'expires_at' => now()->addDays(2)]);
    $later = JobPosting::factory()->create(['title' => 'Closing Later Role', 'expires_at' => now()->addDays(10)]);
    $applied = JobPosting::factory()->create(['title' => 'Applied Closing Role', 'expires_at' => now()->addDay()]);
    $candidate->savedJobs()->attach([$closing->id, $later->id, $applied->id]);
    applicationOf($candidate, ['job_posting_id' => $applied->id]);

    $closingSaved = $this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('closingSaved');

    expect($closingSaved->pluck('title')->all())->toBe(['Closing Soon Role']);
});

test('the profile gaps are the parts a company reads first, never links or the cover', function () {
    $candidate = candidateUser();
    $candidate->candidateProfile->update(['headline' => null, 'bio' => null, 'github_url' => null, 'cover_photo_path' => null]);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    expect($response->viewData('profileGaps'))->toBe(['photo', 'headline', 'about', 'experience', 'skills']);
    $response->assertSee('Add a photo, a headline, About, your experience and your skills to your profile');
});

test('a full profile has no gaps', function () {
    $candidate = candidateUser();
    $candidate->update(['avatar' => 'avatars/rafi.jpg']);
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create();
    $candidate->candidateProfile->skills()->attach(Skill::create(['name' => 'Laravel'])->id, ['proficiency' => 'advanced']);

    $response = $this->actingAs($candidate->fresh())->get(route('candidate.dashboard'));

    expect($response->viewData('profileGaps'))->toBe([]);
});

test('with no skills and a CV in the library, filling the profile from the CV is offered instead of asking for skills', function () {
    $candidate = candidateUser();
    $application = applicationOf($candidate);

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    expect($response->viewData('importableCv')?->id)->toBe($application->resume_document_id)
        ->and($response->viewData('profileGaps'))->not->toContain('skills')->not->toContain('experience');
    $response->assertSee('Fill your profile from your CV')
        ->assertSee(route('candidate.resume-import', $application->resume_document_id), false);
});

test('a company whose hiring the candidate can review is listed until they have reviewed it', function () {
    $candidate = candidateUser();
    $application = applicationOf($candidate, ['outcome_status' => ApplicationOutcomeStatus::Rejected, 'decided_at' => now()->subWeek()]);
    $company = $application->jobPosting->company;

    $this->actingAs($candidate)->get(route('candidate.dashboard'))
        ->assertSee("Review how {$company->name} hired");

    CompanyReview::factory()->create(['application_id' => $application->id]);

    $this->actingAs($candidate)->get(route('candidate.dashboard'))
        ->assertDontSee("Review how {$company->name} hired");
});

test('an application still waiting gives nothing to review yet', function () {
    $candidate = candidateUser();
    applicationOf($candidate);

    expect($this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('reviewable'))->toBeEmpty();
});

test('recently viewed jobs are listed most recent first, at most four, without the ones recommended above', function () {
    $laravel = Skill::create(['name' => 'Laravel']);
    $candidate = candidateUser();
    $candidate->candidateProfile->skills()->attach($laravel->id, ['proficiency' => 'advanced']);

    $recommended = JobPosting::factory()->create(['title' => 'Recommended Role']);
    $recommended->skills()->attach($laravel->id, ['importance' => 'required']);
    JobView::create(['user_id' => $candidate->id, 'job_posting_id' => $recommended->id, 'viewed_at' => now()]);

    foreach (range(1, 5) as $i) {
        JobView::create([
            'user_id' => $candidate->id,
            'job_posting_id' => JobPosting::factory()->create(['title' => "Viewed Role {$i}"])->id,
            'viewed_at' => now()->subDays($i),
        ]);
    }

    $viewed = $this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('recentlyViewed');

    expect($viewed->pluck('title')->all())->toBe(['Viewed Role 1', 'Viewed Role 2', 'Viewed Role 3', 'Viewed Role 4']);
});

test('recently viewed is left out when nothing was viewed', function () {
    $this->actingAs(candidateUser())->get(route('candidate.dashboard'))
        ->assertOk()
        ->assertDontSee('Recently viewed');
});

test('saved jobs and active alerts are counted', function () {
    $candidate = candidateUser();
    $candidate->savedJobs()->attach(JobPosting::factory()->count(2)->create()->pluck('id'));
    JobAlert::factory()->for($candidate)->create();
    JobAlert::factory()->for($candidate)->paused()->create();

    $response = $this->actingAs($candidate)->get(route('candidate.dashboard'));

    expect($response->viewData('savedCount'))->toBe(2)
        ->and($response->viewData('alertCount'))->toBe(1);
});
