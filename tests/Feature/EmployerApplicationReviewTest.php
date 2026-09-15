<?php

use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Enums\SkillImportance;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use Livewire\Livewire;

function applicationFor(Company $company, ?JobPosting $job = null): Application
{
    $job ??= JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();

    return Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);
}

test('someone from another company cannot review applications', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);

    $this->actingAs(employerUser())
        ->get(route('employer.jobs.applications', [
            'company' => $company,
            'job_posting' => $application->jobPosting,
        ]))
        ->assertForbidden();
});

test('a candidate cannot open the employer side of their own application', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);
    $candidate = $application->candidateProfile->user;

    $this->actingAs($candidate)
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertForbidden();
});

test('every member of the hiring team can review, not just managers', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('employer.jobs.applications', [
            'company' => $company,
            'job_posting' => $application->jobPosting,
        ]))
        ->assertOk()
        ->assertSee($application->candidateProfile->user->name);
});

test('applicants are ranked by how well they match', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $vue = Skill::create(['name' => 'Vue', 'slug' => 'vue']);
    $job->skills()->attach([
        $laravel->id => ['importance' => SkillImportance::Required],
        $vue->id => ['importance' => SkillImportance::Required],
    ]);

    $strong = applicationFor($company, $job);
    $strong->candidateProfile->skills()->attach([
        $laravel->id => ['proficiency' => 'expert'],
        $vue->id => ['proficiency' => 'expert'],
    ]);

    $weak = applicationFor($company, $job);
    $weak->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'beginner']]);

    $component = Livewire::actingAs(employerUser($company))
        ->test('pages::employer.applications', ['company' => $company, 'jobPosting' => $job]);

    $ordered = $component->get('applications')->pluck('id')->all();

    expect($ordered[0])->toBe($strong->id);
    expect($ordered[1])->toBe($weak->id);
});

test('moving an applicant forward records who moved them and when', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);
    $reviewer = employerUser($company);

    Livewire::actingAs($reviewer)
        ->test('pages::employer.application-detail', ['company' => $company, 'application' => $application])
        ->set('stage', ApplicationStage::Shortlisted->value)
        ->call('updateStage');

    $application->refresh();

    expect($application->stage)->toBe(ApplicationStage::Shortlisted);

    $event = $application->events()->sole();
    expect($event->from_stage)->toBe(ApplicationStage::New->value);
    expect($event->to_stage)->toBe(ApplicationStage::Shortlisted->value);
    expect($event->changed_by_id)->toBe($reviewer->id);
});

test('setting the same stage again writes no history', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);

    Livewire::actingAs(employerUser($company))
        ->test('pages::employer.application-detail', ['company' => $company, 'application' => $application])
        ->set('stage', ApplicationStage::New->value)
        ->call('updateStage');

    expect($application->events()->count())->toBe(0);
});

test('a plain member cannot move a whole batch at once', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);

    Livewire::actingAs(employerUser($company, MembershipRole::Member))
        ->test('pages::employer.applications', [
            'company' => $company,
            'jobPosting' => $application->jobPosting,
        ])
        ->set('selected', [$application->id])
        ->call('moveSelected', ApplicationStage::Shortlisted->value)
        ->assertForbidden();

    expect($application->fresh()->stage)->toBe(ApplicationStage::New);
});

test('a manager can move a batch, and each one gets its own history entry', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $first = applicationFor($company, $job);
    $second = applicationFor($company, $job);

    Livewire::actingAs(employerUser($company, MembershipRole::Manager))
        ->test('pages::employer.applications', ['company' => $company, 'jobPosting' => $job])
        ->set('selected', [$first->id, $second->id])
        ->call('moveSelected', ApplicationStage::Interview->value);

    expect($first->fresh()->stage)->toBe(ApplicationStage::Interview);
    expect($second->fresh()->stage)->toBe(ApplicationStage::Interview);
    expect($first->events()->count())->toBe(1);
    expect($second->events()->count())->toBe(1);
});

test('a note is visible to the team and editable only by its author', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);
    $author = employerUser($company);
    $colleague = employerUser($company, MembershipRole::Manager);

    Livewire::actingAs($author)
        ->test('pages::employer.application-detail', ['company' => $company, 'application' => $application])
        ->set('newNote', 'Strong on queues, weaker on testing.')
        ->call('addNote')
        ->assertHasNoErrors();

    $note = $application->notes()->sole();
    expect($note->author_id)->toBe($author->id);

    // The colleague sees it...
    $this->actingAs($colleague)
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertSee('Strong on queues');

    // ...but cannot rewrite it.
    Livewire::actingAs($colleague)
        ->test('pages::employer.application-detail', ['company' => $company, 'application' => $application])
        ->call('startEditing', $note->id)
        ->assertForbidden();
});

test('the candidate never sees the notes written about them', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);
    $author = employerUser($company);

    ApplicationNote::factory()->create([
        'application_id' => $application->id,
        'author_id' => $author->id,
        'note' => 'Private hiring commentary.',
    ]);

    $this->actingAs($application->candidateProfile->user)
        ->get(route('candidate.applications.show', $application))
        ->assertOk()
        ->assertDontSee('Private hiring commentary.');
});

test('a CV is served only to the company that received the application', function () {
    $company = Company::factory()->create();
    $application = applicationFor($company);

    $this->actingAs(employerUser())
        ->get(route('employer.applications.resume', ['company' => $company, 'application' => $application]))
        ->assertForbidden();
});
