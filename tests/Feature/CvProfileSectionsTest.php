<?php

use App\Actions\AnonymizeUser;
use App\Models\Application;
use App\Models\Award;
use App\Models\CandidateLanguage;
use App\Models\Company;
use App\Models\CoverLetter;
use App\Models\CvVersion;
use App\Models\JobPosting;
use App\Models\Publication;
use App\Models\VolunteerExperience;
use App\Support\CvData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('languages are added with a level, once each, and listed strongest first on the CV', function () {
    $candidate = candidateUser();

    $section = Livewire::actingAs($candidate)->test('profile.languages-section')
        ->call('create')->set('name', 'English')->set('proficiency', 'c1')->call('save')
        ->assertHasNoErrors()->assertDispatched('profile-updated')
        ->call('create')->set('name', 'Bengali')->set('proficiency', 'native')->call('save')
        ->call('create')->set('name', 'English')->set('proficiency', 'a1')->call('save')
        ->assertHasErrors(['name' => 'unique'])
        ->call('create')->set('name', 'German')->set('proficiency', 'z9')->call('save')
        ->assertHasErrors(['proficiency']);

    $section->assertSee('Advanced (C1)')->assertSee('Native');

    expect(array_column(CvData::fromProfile($candidate->candidateProfile->fresh())->languages, 'name'))->toBe(['Bengali', 'English']);
});

test('awards, volunteering and publications are added, changed and deleted', function (string $component, string $model, array $fields, string $seen) {
    $candidate = candidateUser();
    $page = Livewire::actingAs($candidate)->test($component)->call('create');

    foreach ($fields as $field => $value) {
        $page->set($field, $value);
    }

    $page->call('save')->assertHasNoErrors()->assertSee($seen);

    $entry = $model::query()->sole();
    $page->call('edit', $entry->id)->call('delete');

    expect($model::query()->exists())->toBeFalse();
})->with([
    'award' => ['profile.awards-section', Award::class, ['title' => 'First in batch', 'issuer' => 'Ostad', 'awardedOn' => '2025-06-01'], 'First in batch'],
    'volunteering' => ['profile.volunteering-section', VolunteerExperience::class, ['role' => 'Mentor', 'organization' => 'Code Club', 'startDate' => '2024-01-01'], 'Code Club'],
    'publication' => ['profile.publications-section', Publication::class, ['title' => 'Queues in Laravel', 'url' => 'https://dev.to/queues'], 'dev.to/queues'],
]);

test('the new sections check their fields', function () {
    Livewire::actingAs(candidateUser())->test('profile.awards-section')
        ->call('create')->set('awardedOn', now()->addMonth()->format('Y-m-d'))->call('save')
        ->assertHasErrors(['title', 'awardedOn']);

    Livewire::actingAs(candidateUser())->test('profile.volunteering-section')
        ->call('create')->set('role', 'Mentor')->set('organization', 'X')->set('endDate', '2024-01-01')->call('save')
        ->assertHasErrors(['startDate']);

    Livewire::actingAs(candidateUser())->test('profile.publications-section')
        ->call('create')->set('title', 'A paper')->set('url', 'javascript:alert(1)')->call('save')
        ->assertHasErrors(['url']);
});

test('another candidate\'s entries cannot be opened', function (string $component, string $model) {
    $other = $model::factory()->create();

    expect(fn () => Livewire::actingAs(candidateUser())->test($component)->call('edit', $other->id))
        ->toThrow(ModelNotFoundException::class);
})->with([
    ['profile.languages-section', CandidateLanguage::class],
    ['profile.awards-section', Award::class],
    ['profile.volunteering-section', VolunteerExperience::class],
    ['profile.publications-section', Publication::class],
]);

test('the profile page and the builder offer the new sections', function () {
    $candidate = candidateUser();

    $this->actingAs($candidate)->get(route('candidate.profile.edit'))
        ->assertSeeLivewire('profile.languages-section')
        ->assertSeeLivewire('profile.awards-section')
        ->assertSeeLivewire('profile.volunteering-section')
        ->assertSeeLivewire('profile.publications-section');

    $this->actingAs($candidate)->get(route('candidate.cv-builder'))
        ->assertSeeLivewire('profile.languages-section')
        ->assertSeeLivewire('cv.extras');
});

test('a company reads an applicant\'s languages, awards, volunteering and publications', function () {
    $company = Company::factory()->create();
    $employer = employerUser($company);
    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    CandidateLanguage::factory()->for($profile)->create(['name' => 'Japanese', 'level' => 'b1']);
    Award::factory()->for($profile)->create(['title' => 'Hackathon winner']);
    VolunteerExperience::factory()->for($profile)->create(['role' => 'Mentor']);
    Publication::factory()->for($profile)->create(['title' => 'Queues in Laravel']);
    $application = Application::factory()->create(['job_posting_id' => $job->id, 'candidate_profile_id' => $profile->id]);

    $this->actingAs($employer)->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertSee('Japanese')->assertSee('Intermediate (B1)')
        ->assertSee('Hackathon winner')->assertSee('Mentor')->assertSee('Queues in Laravel');
});

test('erasing an account removes the new sections, saved CVs and cover letters', function () {
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    CandidateLanguage::factory()->for($profile)->create();
    Award::factory()->for($profile)->create();
    VolunteerExperience::factory()->for($profile)->create();
    Publication::factory()->for($profile)->create();
    CvVersion::factory()->for($profile)->published()->create(['extras' => ['personal' => ['father_name' => 'Abdul Karim']]]);
    CoverLetter::factory()->for($profile)->create();

    app(AnonymizeUser::class)($candidate);

    expect(CandidateLanguage::count() + Award::count() + VolunteerExperience::count() + Publication::count() + CvVersion::count() + CoverLetter::count())->toBe(0);
});
