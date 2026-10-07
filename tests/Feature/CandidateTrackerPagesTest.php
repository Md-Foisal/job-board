<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Company;
use App\Models\Document;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\ScreeningAnswer;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * The candidate's own tracker -- applications, saved jobs, job alerts --
 * and the settings pages every user has, wherever they open them from.
 */

/**
 * An application by $candidate to a posting that stays open for a month,
 * so no closing-soon badge or closed note appears unless a test asks.
 */
function trackedApplication(User $candidate, array $attributes = [], array $job = []): Application
{
    return Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create($job + ['expires_at' => now()->addMonth()])->id,
        ...$attributes,
    ]);
}

test('the candidate sees one status per application, in the words of a job tracker', function (ApplicationStage $stage, ApplicationOutcomeStatus $outcome, ?int $decidedMinutesAgo, string $expected) {
    $application = (new Application)->forceFill([
        'stage' => $stage,
        'outcome_status' => $outcome,
        'decided_at' => $decidedMinutesAgo === null ? null : now()->subMinutes($decidedMinutesAgo),
    ]);

    $html = Blade::render('<x-application-status :application="$application" for-candidate />', ['application' => $application]);

    expect(trim(strip_tags($html)))->toBe($expected);
})->with([
    'sent, not looked at yet' => [ApplicationStage::New, ApplicationOutcomeStatus::Active, null, 'Applied'],
    'shortlisted' => [ApplicationStage::Shortlisted, ApplicationOutcomeStatus::Active, null, 'Shortlisted'],
    'interview' => [ApplicationStage::Interview, ApplicationOutcomeStatus::Active, null, 'Interview'],
    'offer' => [ApplicationStage::Offer, ApplicationOutcomeStatus::Active, null, 'Offer'],
    'hired' => [ApplicationStage::Offer, ApplicationOutcomeStatus::Hired, 60, 'Hired'],
    'turned down' => [ApplicationStage::Interview, ApplicationOutcomeStatus::Rejected, 60, 'Not selected'],
    'turned down, still inside the undo window' => [ApplicationStage::Shortlisted, ApplicationOutcomeStatus::Rejected, 2, 'Shortlisted'],
    'withdrawn' => [ApplicationStage::New, ApplicationOutcomeStatus::Withdrawn, null, 'Withdrawn'],
]);

test('applications are split into an Active tab and a Closed tab', function () {
    $candidate = candidateUser();
    trackedApplication($candidate, [], ['title' => 'Open Role']);
    trackedApplication($candidate, ['outcome_status' => ApplicationOutcomeStatus::Rejected, 'decided_at' => now()->subHour()], ['title' => 'Turned Down Role']);
    trackedApplication($candidate, ['outcome_status' => ApplicationOutcomeStatus::Withdrawn], ['title' => 'Withdrawn Role']);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index'))
        ->assertOk()
        ->assertSee('Open Role')
        ->assertDontSee('Turned Down Role')
        ->assertDontSee('Withdrawn Role')
        ->assertSee('aria-current="page"', false);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index', ['status' => 'closed']))
        ->assertOk()
        ->assertSee('Turned Down Role')
        ->assertSee('Not selected')
        ->assertSee('Withdrawn Role')
        ->assertDontSee('Open Role');
});

test('a dashboard count opens the Active tab narrowed to one step', function () {
    $candidate = candidateUser();
    trackedApplication($candidate, [], ['title' => 'Waiting Role']);
    trackedApplication($candidate, ['stage' => ApplicationStage::Interview], ['title' => 'Interview Role']);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index', ['status' => 'applied']))
        ->assertOk()
        ->assertSee('Waiting Role')
        ->assertDontSee('Interview Role')
        ->assertSee('Show all active');
});

test('an empty Active tab tells someone with history where it went', function () {
    $candidate = candidateUser();
    trackedApplication($candidate, ['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index'))
        ->assertOk()
        ->assertSee('Nothing in progress right now.')
        ->assertDontSee("You haven't applied", false);
});

test('an application still waiting on a job that has closed says so', function () {
    $candidate = candidateUser();
    $application = trackedApplication($candidate, [], ['title' => 'Closed Role', 'availability_status' => AvailabilityStatus::Closed]);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index'))
        ->assertSeeInOrder(['Closed Role', 'Job closed']);

    // The job page would answer "not found" now, so it is not linked.
    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $application))
        ->assertOk()
        ->assertSee('Job closed')
        ->assertDontSee('href="'.route('jobs.show', $application->jobPosting).'"', false);
});

test('the application page shows what was sent, even after the CV left the library', function () {
    Storage::fake('local');
    $candidate = candidateUser();
    $document = Document::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::Cv,
        'original_filename' => 'rafi-ahmed-cv.pdf',
    ]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.4 test');

    $application = trackedApplication($candidate, [
        'resume_document_id' => $document->id,
        'cover_letter' => '<p>I build Laravel apps.</p>',
    ]);
    $question = ScreeningQuestion::create(['job_posting_id' => $application->job_posting_id, 'question_text' => 'Can you start in November?', 'display_order' => 1]);
    ScreeningAnswer::create(['application_id' => $application->id, 'screening_question_id' => $question->id, 'answer_text' => 'Yes, from the 3rd.']);

    $document->delete();

    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $application))
        ->assertOk()
        ->assertSee('rafi-ahmed-cv.pdf')
        ->assertSee('I build Laravel apps.')
        ->assertSee('Can you start in November?')
        ->assertSee('Yes, from the 3rd.')
        ->assertSee('href="'.route('candidate.applications.resume', $application).'"', false);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.resume', $application))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(candidateUser())
        ->get(route('candidate.applications.resume', $application))
        ->assertForbidden();

    $this->actingAs(employerUser($application->jobPosting->company))
        ->get(route('candidate.applications.resume', $application))
        ->assertForbidden();
});

test('withdrawing is offered in the page header, and only while it can be done', function () {
    $candidate = candidateUser();
    $open = trackedApplication($candidate);
    $withdrawn = trackedApplication($candidate, ['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    $this->actingAs($candidate)->get(route('candidate.applications.show', $open))
        ->assertSee('Withdraw this application?');

    $this->actingAs($candidate)->get(route('candidate.applications.show', $withdrawn))
        ->assertDontSee('Withdraw this application?');
});

test('saved jobs are listed latest saved first, and say which were applied to', function () {
    $candidate = candidateUser();
    $older = JobPosting::factory()->create(['title' => 'Saved Last Week', 'expires_at' => now()->addMonth()]);
    $newer = JobPosting::factory()->create(['title' => 'Saved Today', 'expires_at' => now()->addMonth()]);

    $candidate->savedJobs()->attach($older->id, ['created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()]);
    $candidate->savedJobs()->attach($newer->id);
    Application::factory()->create(['candidate_profile_id' => $candidate->candidateProfile->id, 'job_posting_id' => $older->id]);

    $this->actingAs($candidate)
        ->get(route('candidate.saved-jobs.index'))
        ->assertOk()
        ->assertSeeInOrder(['Saved Today', 'Saved Last Week', 'Applied']);

    expect($candidate->savedJobs()->find($newer->id)->pivot->created_at)->not->toBeNull();
});

test('a job card says when a posting closes within three days, and not before', function () {
    $render = fn (JobPosting $jobPosting) => Blade::render('<x-job-card :job-posting="$jobPosting" />', ['jobPosting' => $jobPosting]);

    expect($render(JobPosting::factory()->create(['expires_at' => now()->addDays(2)->addHour()])))
        ->toContain('Closes in 2 days');

    expect($render(JobPosting::factory()->create(['expires_at' => now()->addDays(10)])))
        ->not->toContain('Closes in');
});

test('the new-alert button is shut once the limit is reached, and delete is inside the edit dialog', function () {
    $candidate = candidateUser();
    JobAlert::factory()->for($candidate)->count(JobAlert::MAX_PER_CANDIDATE - 1)->create();

    $page = Livewire::actingAs($candidate)->test('pages::candidate.job-alerts')->assertViewHas('atLimit', false);

    $last = JobAlert::factory()->for($candidate)->create();
    $page->call('$refresh')->assertViewHas('atLimit', true);

    $page->call('edit', $last->id)
        ->assertSet('showModal', true)
        ->assertSee('Delete')
        ->call('delete', $last->id)
        ->assertSet('showModal', false);

    expect($last->fresh())->toBeNull();
});

test('settings opened from a company workspace stay inside it', function () {
    $company = Company::factory()->create();
    $member = employerUser($company);

    $this->actingAs($member)
        ->get(route('employer.dashboard', $company))
        ->assertSee('href="'.route('profile.edit', ['company' => $company->slug]).'"', false);

    $this->actingAs($member)
        ->get(route('profile.edit', ['company' => $company->slug]))
        ->assertOk()
        ->assertSee('href="'.route('employer.dashboard', $company).'"', false)
        ->assertSee('href="'.route('security.edit', ['company' => $company->slug]).'"', false);
});

test('settings never open inside a company the person is not a member of', function () {
    $member = employerUser();
    $stranger = Company::factory()->create();

    $this->actingAs($member)
        ->get(route('profile.edit', ['company' => $stranger->slug]))
        ->assertOk()
        ->assertDontSee(route('employer.dashboard', $stranger), false)
        ->assertDontSee($stranger->name);
});
