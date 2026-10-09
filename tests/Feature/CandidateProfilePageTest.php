<?php

use App\Enums\DocumentType;
use App\Enums\ProficiencyLevel;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use App\Support\ExperienceDuration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('the profile reads in LinkedIn order: intro, About, Experience, Education, Skills', function () {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update(['headline' => 'Support Lead', 'bio' => 'I answer people.']);

    $this->actingAs($candidate)
        ->get(route('candidate.profile.edit'))
        ->assertOk()
        ->assertSeeInOrder(['My profile', $candidate->name, 'Support Lead', 'About', 'I answer people.', 'Experience', 'Education', 'Skills', 'Contact for your CVs']);
});

test('a role shows how long it lasted', function () {
    $candidate = candidateUser()->fresh();
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create([
        'job_title' => 'Support Agent',
        'start_date' => '2021-01-01',
        'end_date' => '2022-06-01',
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.profile.edit'))
        ->assertSeeInOrder(['Support Agent', 'Jan 2021', 'Jun 2022', '1 yr 6 mos']);
});

test('one role counts its months the way a CV states them', function (string $start, ?string $end, int $months, string $label) {
    $record = new ExperienceRecord(['start_date' => $start, 'end_date' => $end]);
    $today = CarbonImmutable::parse('2026-10-07');

    expect(ExperienceDuration::ofRole($record, $today))->toBe($months)
        ->and(ExperienceDuration::label($months))->toBe($label);
})->with([
    'a single month' => ['2026-03-01', '2026-03-31', 1, '1 mo'],
    'a calendar year' => ['2021-01-01', '2021-12-01', 12, '1 yr'],
    'years and months' => ['2023-01-01', '2025-06-01', 30, '2 yrs 6 mos'],
    'still going' => ['2026-01-01', null, 10, '10 mos'],
    'not started yet' => ['2027-01-01', null, 0, ''],
]);

test('the preview shows the profile as a company reads it, without what stays private', function () {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update([
        'headline' => 'Support Lead',
        'phone' => '+44 7700 900456',
        'location' => 'Lalmonirhat, Bangladesh',
        'cover_photo_path' => 'candidate-covers/cover.jpg',
    ]);
    $candidate->candidateProfile->skills()->attach(Skill::create(['name' => 'Zendesk'])->id, ['proficiency' => ProficiencyLevel::Advanced]);

    $this->actingAs($candidate)
        ->get(route('candidate.profile.preview'))
        ->assertOk()
        ->assertSee('Support Lead')
        ->assertSeeInOrder(['Zendesk', 'Advanced'])
        ->assertSee('No work experience on their profile.')
        ->assertDontSee('7700 900456')
        ->assertDontSee('Lalmonirhat')
        ->assertDontSee('candidate-covers/cover.jpg')
        ->assertDontSee('Edit cover')
        ->assertSee('Back to');
});

test('location shows on the owner\'s own intro card', function () {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update(['location' => 'Feni, Bangladesh']);

    $this->actingAs($candidate)->get(route('candidate.profile.edit'))->assertSee('Feni, Bangladesh');
});

test('experience is added, changed and deleted from the profile itself', function () {
    $candidate = candidateUser();

    $section = Livewire::actingAs($candidate)->test('profile.experience-section')
        ->call('create')
        ->set('jobTitle', 'Support Agent')
        ->set('companyName', 'Callfield')
        ->set('startDate', '2022-01-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertSee('Support Agent');

    $record = $candidate->candidateProfile->experienceRecords()->sole();

    $section->call('edit', $record->id)
        ->assertSet('jobTitle', 'Support Agent')
        ->set('jobTitle', 'Senior Support Agent')
        ->call('save')
        ->assertSee('Senior Support Agent');

    expect($record->refresh()->job_title)->toBe('Senior Support Agent');

    $section->call('edit', $record->id)->call('delete');

    expect($candidate->candidateProfile->experienceRecords()->exists())->toBeFalse();
});

test('a role needs a title, a company and a start no later than its end', function () {
    Livewire::actingAs(candidateUser())->test('profile.experience-section')
        ->call('create')
        ->set('startDate', '2024-05-01')
        ->set('endDate', '2024-01-01')
        ->call('save')
        ->assertHasErrors(['jobTitle' => 'required', 'companyName' => 'required', 'endDate' => 'after_or_equal']);
});

test('another candidate\'s experience cannot be opened', function () {
    $other = ExperienceRecord::factory()->create();

    expect(fn () => Livewire::actingAs(candidateUser())->test('profile.experience-section')->call('edit', $other->id))
        ->toThrow(ModelNotFoundException::class);
});

test('education is added and deleted from the profile itself', function () {
    $candidate = candidateUser();

    $section = Livewire::actingAs($candidate)->test('profile.education-section')
        ->call('create')
        ->set('institutionName', 'University of Dhaka')
        ->set('degree', 'BA')
        ->set('startDate', '2015-07-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('University of Dhaka');

    $record = $candidate->candidateProfile->educationRecords()->sole();

    $section->call('edit', $record->id)->call('delete');

    expect(EducationRecord::query()->exists())->toBeFalse();
});

test('skills are added at the chosen level, re-rated and removed', function () {
    $candidate = candidateUser();
    $laravel = Skill::create(['name' => 'Laravel']);

    $section = Livewire::actingAs($candidate)->test('profile.skills-section')
        ->call('startAdding')
        ->set('newLevel', 'advanced')
        ->set('q', 'lara')
        ->assertSee('Laravel')
        ->call('add', $laravel->id)
        ->assertSet('q', '');

    expect($candidate->candidateProfile->skills()->sole()->pivot->proficiency)->toBe(ProficiencyLevel::Advanced);

    $section->call('edit', $laravel->id)
        ->assertSet('editingLevel', 'advanced')
        ->set('editingLevel', 'beginner')
        ->call('saveLevel');

    expect($candidate->candidateProfile->skills()->sole()->pivot->proficiency)->toBe(ProficiencyLevel::Beginner);

    $section->call('edit', $laravel->id)->call('remove');

    expect($candidate->candidateProfile->skills()->exists())->toBeFalse();
});

test('a skill already on the profile is not offered again, and a retired skill cannot be added', function () {
    $candidate = candidateUser();
    $laravel = Skill::create(['name' => 'Laravel']);
    $candidate->candidateProfile->skills()->attach($laravel->id, ['proficiency' => 'advanced']);
    $retired = Skill::create(['name' => 'Laravel Mix']);
    $retired->delete();

    $section = Livewire::actingAs($candidate)->test('profile.skills-section')->set('q', 'lara');

    expect($section->instance()->results)->toBeEmpty();
    expect(fn () => $section->call('add', $retired->id))->toThrow(ModelNotFoundException::class);
});

test('an empty section offers to fill it from a readable CV in the library', function () {
    $candidate = candidateUser();
    $cv = Document::factory()->for($candidate->candidateProfile)->create(['document_type' => DocumentType::Cv]);

    Livewire::actingAs($candidate)->test('profile.experience-section')
        ->assertSee('Fill from your CV')
        ->assertSee(route('candidate.resume-import', $cv), false);
});

test('a CV that cannot be read is not offered for filling the profile', function () {
    $candidate = candidateUser();
    Document::factory()->for($candidate->candidateProfile)->create([
        'document_type' => DocumentType::Cv,
        'file_path' => 'documents/old-cv.doc',
        'original_filename' => 'old-cv.doc',
    ]);

    Livewire::actingAs($candidate)->test('profile.skills-section')->assertDontSee('Fill from your CV');
});

test('the experience, education and skills pages are the same sections, one step below the profile', function (string $route) {
    $this->actingAs(candidateUser()->fresh())
        ->get(route($route))
        ->assertOk()
        ->assertSeeInOrder(['Back to', 'My profile'])
        ->assertSee(route('candidate.profile.edit'), false);
})->with(['candidate.experience.index', 'candidate.education.index', 'candidate.skills.edit']);

test('the owner is offered the links still missing, and a company sees only the filled ones', function () {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update(['portfolio_url' => 'https://rafi.example', 'github_url' => null, 'linkedin_url' => null]);

    $this->actingAs($candidate)->get(route('candidate.profile.edit'))
        ->assertSee('Add your GitHub or LinkedIn link');

    $this->actingAs($candidate)->get(route('candidate.profile.preview'))
        ->assertSee('rafi.example')
        ->assertDontSee('Add your GitHub');
});
