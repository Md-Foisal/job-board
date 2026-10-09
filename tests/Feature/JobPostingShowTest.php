<?php

use App\Enums\DocumentType;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Support\ClosingDate;

test('guest can view a publicly visible job posting', function () {
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
});

test('guest cannot view a draft job posting', function () {
    $job = JobPosting::factory()->draft()->create();

    $response = $this->get(route('jobs.show', $job));

    $response->assertNotFound();
});

test('guest cannot view a job posting pending moderation', function () {
    $job = JobPosting::factory()->pendingModeration()->create();

    $response = $this->get(route('jobs.show', $job));

    $response->assertNotFound();
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

    $response->assertNotFound();
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

test('a guest is offered Apply, which goes by way of signing in', function () {
    $job = JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee(route('jobs.apply', $job), false)
        ->assertSee('Sign in or create a free account to apply.')
        ->assertSee('data-sticky-apply', false);
});

test('a candidate who can apply gets the button and the bar that follows it on a phone', function () {
    $job = JobPosting::factory()->create();

    $this->actingAs(candidateUser())
        ->get(route('jobs.show', $job))
        ->assertSee(route('jobs.apply', $job), false)
        ->assertSee('data-sticky-apply', false)
        ->assertDontSee('Sign in or create a free account to apply.');
});

test('a candidate who has applied is pointed to their application instead of a button that would refuse them', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $application = Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'resume_document_id' => Document::factory()->create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'document_type' => DocumentType::Cv,
        ])->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('jobs.show', $job))
        ->assertSee('You applied on')
        ->assertSee(route('candidate.applications.show', $application), false)
        ->assertDontSee(route('jobs.apply', $job), false)
        ->assertDontSee('data-sticky-apply', false);
});

test('a company member previewing a draft is told only the team can see it, and can edit it', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->draft()->create();

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Only your team can see this page.')
        ->assertSee(route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $job]), false)
        ->assertDontSee(route('jobs.apply', $job), false);
});

test('a company member on a live posting sees it as candidates do, without Apply or Report', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();

    $this->actingAs(employerUser($company))
        ->get(route('jobs.show', $job))
        ->assertSee('This is how candidates see your job.')
        ->assertDontSee(route('jobs.apply', $job), false)
        ->assertDontSeeText('Report');
});

test('the company summary keeps a space between paragraphs and decodes entities once', function () {
    $company = Company::factory()->create(['description' => '<p>We cut no-shows.</p><p>Fish &amp; chips on Fridays.</p>']);
    $job = JobPosting::factory()->for($company)->create();

    $this->get(route('jobs.show', $job))
        ->assertSee('We cut no-shows. Fish &amp; chips on Fridays.', false);
});

test('someone signed in without a candidate profile is offered one rather than an Apply button', function () {
    $job = JobPosting::factory()->create();

    $this->actingAs(employerUser())
        ->get(route('jobs.show', $job))
        ->assertSee('Start a candidate profile')
        ->assertSee(route('candidate.start'), false)
        ->assertDontSee(route('jobs.apply', $job), false);
});

test('the job details block lists the facts a seeker filters on', function () {
    $company = Company::factory()->create(['timezone' => 'UTC']);
    $job = JobPosting::factory()->for($company)->create([
        'location_city' => 'London',
        'location_country' => 'United Kingdom',
        'min_experience_years' => 3,
        'expires_at' => ClosingDate::endOf(now()->addDays(10)->toDateString(), $company),
    ]);
    $job->categories()->attach(Category::create(['name' => 'Engineering', 'slug' => 'engineering']));

    $this->get(route('jobs.show', $job))
        ->assertSeeInOrder(['Job details', 'Job type', 'Workplace', 'Location', 'London, United Kingdom', 'Experience', '3+ years', 'Category', 'Engineering', 'Posted', 'Closes', now()->addDays(10)->format('j M Y')])
        ->assertDontSee('End of the day in');
});

test('a closing day in another zone says whose day it is', function () {
    $company = Company::factory()->create(['timezone' => 'Asia/Dhaka']);
    $job = JobPosting::factory()->for($company)->create();

    $this->get(route('jobs.show', $job))->assertSee('End of the day in Asia/Dhaka (GMT+06:00)');
});

test('skills are split into required and nice to have', function () {
    $job = JobPosting::factory()->create();
    $job->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['importance' => 'required']);
    $job->skills()->attach(Skill::firstOrCreate(['name' => 'Vue']), ['importance' => 'nice-to-have']);

    $this->get(route('jobs.show', $job))
        ->assertSeeInOrder(['Skills', 'Required', 'Laravel', 'Nice to have', 'Vue']);
});

test('similar jobs are other open jobs in the same category', function () {
    $category = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $job = JobPosting::factory()->create(['title' => 'Backend Developer']);
    $job->categories()->attach($category);

    $sibling = JobPosting::factory()->create(['title' => 'Platform Engineer']);
    $sibling->categories()->attach($category);
    $closed = JobPosting::factory()->closed()->create(['title' => 'Closed Engineer']);
    $closed->categories()->attach($category);
    JobPosting::factory()->create(['title' => 'Unrelated Designer'])
        ->categories()->attach(Category::create(['name' => 'Design', 'slug' => 'design']));

    $this->get(route('jobs.show', $job))
        ->assertSeeInOrder(['Similar jobs', 'Platform Engineer'])
        ->assertDontSee('Closed Engineer')
        ->assertDontSee('Unrelated Designer');
});

test('a job with no category suggests nothing rather than whatever is newest', function () {
    $job = JobPosting::factory()->create();
    JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))->assertDontSee('Similar jobs');
});

test('the company section links to the company with its open job count', function () {
    $company = Company::factory()->create(['name' => 'Fernhill Software']);
    $job = JobPosting::factory()->for($company)->create();
    JobPosting::factory()->for($company)->create();

    $this->get(route('jobs.show', $job))
        ->assertSeeInOrder(['About the company', 'Fernhill Software', 'See the company and its 2 open jobs']);
});
