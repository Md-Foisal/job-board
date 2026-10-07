<?php

use App\Actions\AnonymizeUser;
use App\Actions\RenderCvPdf;
use App\Enums\CvTemplate;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Certification;
use App\Models\Company;
use App\Models\Document;
use App\Models\JobPosting;
use App\Models\Project;
use App\Support\CvData;
use App\Support\CvDesign;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('projects are added, changed and deleted from the profile', function () {
    $candidate = candidateUser();

    $section = Livewire::actingAs($candidate)->test('profile.projects-section')
        ->call('create')
        ->set('name', 'Shiftboard')
        ->set('url', 'https://shiftboard.example')
        ->set('sourceUrl', 'https://github.com/rafi/shiftboard')
        ->set('startDate', '2025-01-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-updated')
        ->assertSee('Shiftboard')
        ->assertSee('shiftboard.example');

    $project = $candidate->candidateProfile->projects()->sole();

    $section->call('edit', $project->id)
        ->assertSet('sourceUrl', 'https://github.com/rafi/shiftboard')
        ->set('name', 'Shiftboard 2')
        ->call('save');

    expect($project->refresh()->name)->toBe('Shiftboard 2');

    $section->call('edit', $project->id)->call('delete');

    expect(Project::query()->exists())->toBeFalse();
});

test('a project link must be a web address, and an end date needs a start', function () {
    Livewire::actingAs(candidateUser())->test('profile.projects-section')
        ->call('create')
        ->set('name', 'Shiftboard')
        ->set('url', 'javascript:alert(1)')
        ->set('sourceUrl', 'ftp://example.com/code')
        ->set('endDate', '2025-01-01')
        ->call('save')
        ->assertHasErrors(['url', 'sourceUrl', 'startDate' => 'required_with']);
});

test('another candidate\'s project cannot be opened', function () {
    $other = Project::factory()->create();

    expect(fn () => Livewire::actingAs(candidateUser())->test('profile.projects-section')->call('edit', $other->id))
        ->toThrow(ModelNotFoundException::class);
});

test('a certification needs a name and an issuer, an issue date not in the future, and an expiry after it', function () {
    Livewire::actingAs(candidateUser())->test('profile.certifications-section')
        ->call('create')
        ->set('issuedOn', now()->addMonth()->toDateString())
        ->set('expiresOn', now()->subYear()->toDateString())
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'issuer' => 'required', 'issuedOn' => 'before_or_equal', 'expiresOn' => 'after_or_equal']);
});

test('a certification is added and shows whether it has run out', function () {
    $candidate = candidateUser();

    Livewire::actingAs($candidate)->test('profile.certifications-section')
        ->call('create')
        ->set('name', 'AWS Certified Cloud Practitioner')
        ->set('issuer', 'Amazon Web Services')
        ->set('issuedOn', '2022-03-01')
        ->set('expiresOn', '2025-03-01')
        ->set('credentialUrl', 'https://aws.example/verify/123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Amazon Web Services')
        ->assertSee('Expired Mar 2025')
        ->assertSee('Show credential');

    expect(Certification::query()->sole()->hasExpired())->toBeTrue();
});

test('a company reads the projects and certifications of an applicant', function () {
    $company = Company::factory()->create();
    $profile = candidateUser()->candidateProfile;
    Project::factory()->for($profile)->create(['name' => 'Shiftboard', 'source_url' => 'https://github.com/rafi/shiftboard']);
    Certification::factory()->for($profile)->create(['name' => 'Laravel Certification', 'issuer' => 'Laravel']);
    $application = Application::factory()->create([
        'job_posting_id' => JobPosting::factory()->for($company)->create()->id,
        'candidate_profile_id' => $profile->id,
    ]);

    $this->actingAs(employerUser($company))
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertSeeInOrder(['Licences & certifications', 'Laravel Certification', 'Projects', 'Shiftboard'])
        ->assertSee('github.com/rafi/shiftboard');
});

test('the CV lists projects with their links as text, and certifications with their dates', function () {
    $profile = candidateUser()->candidateProfile;
    Project::factory()->for($profile)->create(['name' => 'Older', 'start_date' => '2022-01-01', 'end_date' => '2022-06-01', 'url' => null, 'source_url' => 'javascript:alert(1)']);
    Project::factory()->for($profile)->create(['name' => 'Ongoing', 'start_date' => '2024-01-01', 'end_date' => null, 'url' => 'https://ongoing.example', 'source_url' => null]);
    Certification::factory()->for($profile)->create(['name' => 'Cert', 'issued_on' => '2024-02-01', 'expires_on' => null]);

    $cv = CvData::fromProfile($profile->fresh());

    expect(collect($cv->projects)->pluck('name')->all())->toBe(['Ongoing', 'Older'])
        ->and($cv->projects[0]['links'][0])->toBe(['label' => 'Live', 'url' => 'https://ongoing.example', 'text' => 'ongoing.example'])
        ->and($cv->projects[1]['links'][0]['url'])->toBeNull()
        ->and($cv->certifications[0]['dates'])->toBe('Issued Feb 2024');
});

test('a profile with only a project is enough to build a CV', function () {
    $profile = candidateUser()->candidateProfile;
    Project::factory()->for($profile)->create();

    expect(CvData::fromProfile($profile->fresh())->hasContent())->toBeTrue();
});

test('the design keeps only what is known and fills in the order', function () {
    $design = CvDesign::from('fancy', 'neon', ['skills', 'nonsense', 'skills'], ['summary', 'nonsense']);

    expect($design->template)->toBe(CvTemplate::Classic)
        ->and($design->accent)->toBe('ink')
        ->and($design->order[0])->toBe('skills')
        ->and($design->order)->toHaveCount(6)
        ->and($design->hidden)->toBe(['summary'])
        ->and(collect($design->sections())->pluck('value'))->not->toContain('summary');
});

test('Classic prints in black whatever colour was picked; the others use it', function () {
    expect(CvDesign::from('classic', 'teal')->colour())->toBe('#111827')
        ->and(CvDesign::from('modern', 'teal')->colour())->toBe('#0F766E')
        ->and(CvDesign::from('creative', 'teal')->tint())->toMatch('/^#[0-9a-f]{6}$/');
});

test('every template builds a real PDF', function (string $template) {
    $profile = candidateUser()->candidateProfile;
    Project::factory()->for($profile)->create();
    Certification::factory()->for($profile)->create();

    $pdf = app(RenderCvPdf::class)(CvData::fromProfile($profile->fresh()), design: CvDesign::from($template, 'navy'));

    expect($pdf)->toStartWith('%PDF');
})->with(['classic', 'modern', 'creative']);

test('the builder switches template, hides and moves sections, and remembers it', function () {
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update(['bio' => 'I build things.']);
    Project::factory()->for($profile)->create(['name' => 'Shiftboard']);

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('template', 'modern')
        ->set('accent', 'teal');

    expect($page->instance()->preview)->toContain('#0F766E');

    $page->call('toggleSection', 'summary');
    expect($page->instance()->preview)->not->toContain('I build things.');

    $page->call('moveSection', 'projects', -1)->call('moveSection', 'projects', -1);
    expect($page->instance()->design->order[0])->toBe('projects');

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->assertSet('template', 'modern')
        ->assertSet('accent', 'teal')
        ->assertSet('hiddenSections', ['summary']);
});

test('an unknown template sent from the browser falls back to Classic', function () {
    $candidate = candidateUser();

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('template', 'evil')
        ->assertSet('template', 'classic');
});

test('a change made in one of the builder\'s sections shows in the preview', function () {
    $candidate = candidateUser();
    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder');

    Project::factory()->for($candidate->candidateProfile)->create(['name' => 'Fresh Project']);
    $page->dispatch('profile-updated');

    expect($page->instance()->preview)->toContain('Fresh Project');
});

test('the owner previews a PDF or a photo in the browser, but not a Word file', function () {
    Storage::fake('local');
    $candidate = candidateUser();
    $pdf = Document::factory()->for($candidate->candidateProfile)->create(['document_type' => DocumentType::Cv, 'file_path' => 'documents/cv.pdf']);
    $word = Document::factory()->for($candidate->candidateProfile)->create(['document_type' => DocumentType::Cv, 'file_path' => 'documents/cv.docx']);
    Storage::disk('local')->put('documents/cv.pdf', '%PDF-1.4');
    Storage::disk('local')->put('documents/cv.docx', 'word');

    $response = $this->actingAs($candidate)->get(route('candidate.documents.preview', $pdf));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');

    $this->actingAs($candidate)->get(route('candidate.documents.preview', $word))->assertNotFound();

    $this->actingAs(candidateUser())->get(route('candidate.documents.preview', $pdf))->assertForbidden();

    $this->actingAs($candidate)->get(route('candidate.documents.index'))
        ->assertSee(route('candidate.documents.preview', $pdf), false)
        ->assertDontSee(route('candidate.documents.preview', $word), false);
});

test('erasing an account removes its projects and certifications', function () {
    Storage::fake('local');
    Storage::fake('public');
    $candidate = candidateUser();
    Project::factory()->for($candidate->candidateProfile)->create();
    Certification::factory()->for($candidate->candidateProfile)->create();

    app(AnonymizeUser::class)($candidate);

    expect(Project::query()->exists())->toBeFalse()
        ->and(Certification::query()->exists())->toBeFalse();
});
