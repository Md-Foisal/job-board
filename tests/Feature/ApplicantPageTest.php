<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Enums\SkillImportance;
use App\Models\Application;
use App\Models\Company;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create();
});

function applicantFor(JobPosting $job, array $attributes = []): Application
{
    $candidate = candidateUser();

    return Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
        ...$attributes,
    ]);
}

function applicantPage(Company $company, Application $application, array $query = []): string
{
    return route('employer.applications.show', ['company' => $company, 'application' => $application, ...$query]);
}

test('the applicant page opens on the profile, with the CV and the application one tab away', function () {
    $application = applicantFor($this->job, ['cover_letter' => '<p>I ran support for a fintech.</p>']);
    ExperienceRecord::create([
        'candidate_profile_id' => $application->candidate_profile_id,
        'company_name' => 'Brightdesk Ltd',
        'job_title' => 'Customer Support Lead',
        'start_date' => '2023-03-01',
    ]);
    $member = employerUser($this->company);

    $this->actingAs($member)->get(applicantPage($this->company, $application))
        ->assertOk()
        ->assertSee('Customer Support Lead at Brightdesk Ltd')
        ->assertSee('No education on their profile.')
        ->assertDontSee('I ran support for a fintech.')
        ->assertSee(applicantPage($this->company, $application, ['tab' => 'cv']), false);

    $this->actingAs($member)->get(applicantPage($this->company, $application, ['tab' => 'cv']))
        ->assertOk()
        ->assertSee(route('employer.applications.resume.preview', ['company' => $this->company, 'application' => $application]), false)
        ->assertSee(route('employer.applications.resume', ['company' => $this->company, 'application' => $application]), false);

    $this->actingAs($member)->get(applicantPage($this->company, $application, ['tab' => 'application']))
        ->assertOk()
        ->assertSee('I ran support for a fintech.');
});

test('an application sent without a cover letter says so', function () {
    $application = applicantFor($this->job, ['cover_letter' => null]);

    $this->actingAs(employerUser($this->company))
        ->get(applicantPage($this->company, $application, ['tab' => 'application']))
        ->assertSee('They applied without a cover letter.');
});

test('the CV is drawn inline for the company that received it, and for nobody else', function () {
    Storage::fake('local');
    $application = applicantFor($this->job);
    Storage::disk('local')->put($application->resumeDocument->file_path, '%PDF-1.4');
    $preview = route('employer.applications.resume.preview', ['company' => $this->company, 'application' => $application]);

    $response = $this->actingAs(employerUser($this->company))->get($preview)->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');

    $this->actingAs(employerUser())->get($preview)->assertForbidden();
});

test('a Word CV is offered as a download rather than drawn', function () {
    Storage::fake('local');
    $application = applicantFor($this->job);
    $application->resumeDocument->update(['file_path' => 'documents/cv.docx', 'original_filename' => 'cv.docx']);
    Storage::disk('local')->put('documents/cv.docx', 'word');

    $this->actingAs(employerUser($this->company))
        ->get(route('employer.applications.resume.preview', ['company' => $this->company, 'application' => $application]))
        ->assertNotFound();

    $this->actingAs(employerUser($this->company))
        ->get(applicantPage($this->company, $application, ['tab' => 'cv']))
        ->assertSee('This file opens once downloaded');
});

test('the history starts with the application itself, then who moved it', function () {
    $application = applicantFor($this->job);
    $member = employerUser($this->company);

    Livewire::actingAs($member)
        ->test('pages::employer.application-detail', ['company' => $this->company, 'application' => $application])
        ->call('moveTo', ApplicationStage::Interview->value)
        ->assertSeeInOrder(['Applied', $member->name.' moved this to Interview']);
});

test('a stage that does not exist is refused', function () {
    $application = applicantFor($this->job);

    Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.application-detail', ['company' => $this->company, 'application' => $application])
        ->call('moveTo', 'hired')
        ->assertStatus(422);

    expect($application->fresh()->stage)->toBe(ApplicationStage::New);
});

test('previous and next walk the list in the order it was opened in', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $vue = Skill::create(['name' => 'Vue', 'slug' => 'vue']);
    $this->job->skills()->attach([
        $laravel->id => ['importance' => SkillImportance::Required],
        $vue->id => ['importance' => SkillImportance::NiceToHave],
    ]);

    $best = applicantFor($this->job);
    $best->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced'], $vue->id => ['proficiency' => 'advanced']]);
    $middle = applicantFor($this->job);
    $middle->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced']]);
    $weakest = applicantFor($this->job);
    $weakest->candidateProfile->skills()->attach([$vue->id => ['proficiency' => 'beginner']]);

    $around = Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.application-detail', ['company' => $this->company, 'application' => $middle])
        ->get('neighbours');

    expect($around['previous']->is($best))->toBeTrue();
    expect($around['next']->is($weakest))->toBeTrue();
    expect($around['position'])->toBe(2);

    // Opened from the Interview tab, the walk stays inside it.
    $middle->update(['stage' => ApplicationStage::Interview]);
    $weakest->update(['stage' => ApplicationStage::Interview]);

    $this->actingAs(employerUser($this->company))
        ->get(applicantPage($this->company, $middle, ['stage' => 'interview']))
        ->assertSee(applicantPage($this->company, $weakest, ['stage' => 'interview']), false)
        ->assertSee('1 of 2');
});

test('the company reads one status per applicant, not Active beside the stage', function () {
    $open = applicantFor($this->job, ['stage' => ApplicationStage::Shortlisted]);
    applicantFor($this->job, ['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->assertSee('Shortlisted')
        ->assertSee('Withdrawn')
        ->assertDontSee('Active');
});

test('each stage tab counts the open applications there', function () {
    applicantFor($this->job, ['stage' => ApplicationStage::Interview]);
    applicantFor($this->job, ['stage' => ApplicationStage::Interview, 'outcome_status' => ApplicationOutcomeStatus::Rejected]);
    applicantFor($this->job);

    $counts = Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->get('counts');

    expect($counts)->toMatchArray(['all' => 3, 'new' => 1, 'shortlisted' => 0, 'interview' => 1, 'offer' => 0]);
});

test('the board holds the open applications, one column per stage', function () {
    $new = applicantFor($this->job);
    $offer = applicantFor($this->job, ['stage' => ApplicationStage::Offer]);
    applicantFor($this->job, ['outcome_status' => ApplicationOutcomeStatus::Hired, 'stage' => ApplicationStage::Offer]);

    $columns = Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->set('view', 'board')
        ->get('columns');

    expect($columns['new']->pluck('id')->all())->toBe([$new->id]);
    expect($columns['offer']->pluck('id')->all())->toBe([$offer->id]);
});

test('anyone on the team moves a card on the board, but never a closed one', function () {
    $open = applicantFor($this->job);
    $closed = applicantFor($this->job, ['outcome_status' => ApplicationOutcomeStatus::Rejected]);
    $member = employerUser($this->company, MembershipRole::Member);

    $page = Livewire::actingAs($member)
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->set('view', 'board')
        ->call('move', $open->id, ApplicationStage::Shortlisted->value)
        ->assertHasNoErrors();

    expect($open->fresh()->stage)->toBe(ApplicationStage::Shortlisted);
    expect($open->events()->sole()->changed_by_id)->toBe($member->id);

    $page->call('move', $closed->id, ApplicationStage::Shortlisted->value)->assertForbidden();
    expect($closed->fresh()->stage)->toBe(ApplicationStage::New);
});

test('a card from another job cannot be moved through this one', function () {
    $elsewhere = applicantFor(JobPosting::factory()->for($this->company)->create());

    $page = Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job]);

    expect(fn () => $page->call('move', $elsewhere->id, ApplicationStage::Shortlisted->value))
        ->toThrow(ModelNotFoundException::class);
    expect($elsewhere->fresh()->stage)->toBe(ApplicationStage::New);
});

test('an applicant with no skills is not scored, and the list says why', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $this->job->skills()->attach([$laravel->id => ['importance' => SkillImportance::Required]]);
    applicantFor($this->job);

    Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->assertSee('No skills listed')
        ->assertDontSee('% match');
});

test('the skills a job asks for are ticked on the profile, and the missing ones named', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $vue = Skill::create(['name' => 'Vue', 'slug' => 'vue']);
    $this->job->skills()->attach([
        $laravel->id => ['importance' => SkillImportance::Required],
        $vue->id => ['importance' => SkillImportance::Required],
    ]);
    $application = applicantFor($this->job);
    $application->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced']]);

    $this->actingAs(employerUser($this->company))->get(applicantPage($this->company, $application))
        ->assertSee('(this job asks for it)')
        ->assertSee('Ticked: a skill this job asks for')
        ->assertSeeInOrder(['Asked for, not on their profile', 'Vue', '(required)']);
});

test('applicants sort by total experience, overlapping roles counted once', function () {
    $junior = applicantFor($this->job);
    ExperienceRecord::create(['candidate_profile_id' => $junior->candidate_profile_id, 'company_name' => 'A', 'job_title' => 'Agent', 'start_date' => '2025-01-01', 'end_date' => '2025-12-01']);

    $senior = applicantFor($this->job);
    ExperienceRecord::create(['candidate_profile_id' => $senior->candidate_profile_id, 'company_name' => 'B', 'job_title' => 'Lead', 'start_date' => '2019-01-01', 'end_date' => '2023-12-01']);

    // Two roles held at once: twelve months of working time, not twenty-four.
    $sideJob = applicantFor($this->job);
    ExperienceRecord::create(['candidate_profile_id' => $sideJob->candidate_profile_id, 'company_name' => 'C', 'job_title' => 'Agent', 'start_date' => '2024-01-01', 'end_date' => '2024-12-01']);
    ExperienceRecord::create(['candidate_profile_id' => $sideJob->candidate_profile_id, 'company_name' => 'D', 'job_title' => 'Tutor', 'start_date' => '2024-01-01', 'end_date' => '2024-12-01']);

    $none = applicantFor($this->job);

    $order = Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->set('sort', 'experience')
        ->get('applications')
        ->pluck('id')
        ->all();

    // Junior and the side job both come to twelve months; the earlier
    // application stays first.
    expect($order)->toBe([$senior->id, $junior->id, $sideJob->id, $none->id]);
});

test('switching tabs keeps a note that is half written', function () {
    $application = applicantFor($this->job);

    Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.application-detail', ['company' => $this->company, 'application' => $application])
        ->set('newNote', 'Ask about the Zendesk migration')
        ->set('tab', 'cv')
        ->assertSet('newNote', 'Ask about the Zendesk migration')
        ->assertSee(route('employer.applications.resume.preview', ['company' => $this->company, 'application' => $application]), false)
        ->set('tab', 'nonsense')
        ->assertSet('tab', 'profile');
});
