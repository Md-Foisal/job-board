<?php

use App\Actions\RenderCvPdf;
use App\Enums\CvSection;
use App\Enums\CvTemplate;
use App\Models\Award;
use App\Models\CandidateLanguage;
use App\Models\Certification;
use App\Models\CvVersion;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\VolunteerExperience;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvExtras;
use Livewire\Livewire;

function studioCandidate()
{
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update(['bio' => 'I build things that last.', 'headline' => 'Laravel developer']);
    ExperienceRecord::factory()->for($profile)->create(['job_title' => 'Backend Developer', 'company_name' => 'Acme']);
    Project::factory()->for($profile)->create(['name' => 'Shiftboard']);

    return $candidate->fresh();
}

test('size, spacing and margins scale the template\'s own numbers together', function () {
    $design = CvDesign::from('classic', 'ink', type: ['size' => 'large', 'spacing' => 'compact', 'margins' => 'narrow']);

    expect($design->pt(10))->toBe('10.8pt')
        ->and($design->gap(10))->toBe('7mm')
        ->and($design->page('16mm 18mm'))->toBe('10.4mm 11.7mm')
        ->and($design->pageMargins('16mm 18mm'))->toBe([10.4, 11.7, 10.4, 11.7])
        ->and(CvDesign::from('classic', 'ink', type: ['size' => 'huge'])->size)->toBe('medium');
});

test('any colour dark enough to read on white is kept; a light one falls back', function () {
    expect(CvDesign::from('modern', '#7c2d12')->colour())->toBe('#7C2D12')
        ->and(CvDesign::from('modern', '#fde68a')->accent)->toBe('ink')
        ->and(CvDesign::contrastWithWhite('#ffffff'))->toBe(1.0)
        ->and(CvDesign::contrastWithWhite('#000000'))->toBe(21.0);
});

test('fitting on one page tightens spacing, then margins, then type, then stops', function () {
    $design = new CvDesign;
    $steps = [];

    while ($design = $design->tighter()) {
        $steps[] = [$design->spacing, $design->margins, $design->size];
    }

    expect($steps)->toBe([
        ['compact', 'normal', 'medium'],
        ['compact', 'narrow', 'medium'],
        ['compact', 'narrow', 'small'],
    ]);
});

test('headings print in Bengali when chosen, with Bengali labels for fixed words', function () {
    $design = CvDesign::from('classic', 'ink', type: ['headings' => 'bn']);

    expect($design->heading(CvSection::Experience))->toBe('কর্ম-অভিজ্ঞতা')
        ->and($design->label('Contact', 'যোগাযোগ'))->toBe('যোগাযোগ')
        ->and(CvDesign::from('classic', 'ink')->heading(CvSection::Experience))->toBe('Work Experience');
});

test('every template prints every section, its typeface and its page margins', function (CvTemplate $template) {
    $candidate = studioCandidate();
    $profile = $candidate->candidateProfile;
    Certification::factory()->for($profile)->create(['name' => 'AWS Cloud Practitioner']);
    CandidateLanguage::factory()->for($profile)->create(['name' => 'Bengali', 'level' => 'native']);
    Award::factory()->for($profile)->create(['title' => 'First in batch']);
    VolunteerExperience::factory()->for($profile)->create(['role' => 'Mentor', 'organization' => 'Code Club']);
    Publication::factory()->for($profile)->create(['title' => 'Queues in Laravel']);
    $version = CvVersion::factory()->for($profile)->create(['extras' => [
        'interests' => 'Chess and open source',
        'reference_mode' => 'on_request',
        'personal' => ['father_name' => 'Abdul Karim', 'date_of_birth' => '1999-03-12'],
        'declaration' => true,
        'declaration_place' => 'Feni',
        'custom' => [['title' => 'Training', 'items' => [['title' => 'Docker workshop', 'subtitle' => 'BASIS', 'dates' => '2025', 'description' => '']]]],
    ]]);

    $html = view($template->view(), [
        'cv' => CvData::fromProfile($profile->fresh(), $version),
        'photo' => null,
        'design' => CvDesign::from($template->value, 'teal', type: ['font' => 'merriweather', 'margins' => 'wide']),
        'preview' => true,
    ])->render();

    expect($html)
        ->toContain('AWS Cloud Practitioner')->toContain('Bengali')->toContain('Native')
        ->toContain('First in batch')->toContain('Mentor')->toContain('Queues in Laravel')
        ->toContain('Chess and open source')->toContain('Available on request.')
        ->toContain('Abdul Karim')->toContain('12 March 1999')
        ->toContain('Training')->toContain('Docker workshop')
        ->toContain('Signature')->toContain('Feni')
        ->toContain("font-family: 'Merriweather'")
        ->toContain(route('cv-fonts', 'merriweather-latin-400.woff2'))
        ->toContain('data-margins="');
})->with(CvTemplate::cases());

test('every template still builds a real PDF with every new section, on our own server', function (CvTemplate $template) {
    $profile = studioCandidate()->candidateProfile;
    CandidateLanguage::factory()->for($profile)->create(['name' => 'English', 'level' => 'c1']);
    Award::factory()->for($profile)->create();
    $version = CvVersion::factory()->for($profile)->create(['extras' => ['personal' => ['nationality' => 'Bangladeshi'], 'declaration' => true]]);

    $pdf = app(RenderCvPdf::class)(CvData::fromProfile($profile->fresh(), $version), design: CvDesign::from($template->value, 'navy', type: ['font' => 'lato']));

    expect($pdf)->toStartWith('%PDF');
})->with(CvTemplate::cases());

test('the builder saves every design choice with the CV and finds it again', function () {
    $candidate = studioCandidate();

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('template', 'executive')
        ->set('font', 'eb-garamond')
        ->set('size', 'large')
        ->set('spacing', 'roomy')
        ->set('margins', 'wide')
        ->set('headings', 'bn')
        ->set('paper', 'letter');

    $design = $candidate->candidateProfile->cvVersions()->sole()->design;

    expect($design)->toMatchArray(['template' => 'executive', 'font' => 'eb-garamond', 'size' => 'large', 'spacing' => 'roomy', 'margins' => 'wide', 'headings' => 'bn', 'paper' => 'letter']);

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->assertSet('font', 'eb-garamond')
        ->assertSet('headings', 'bn')
        ->assertSet('paper', 'letter');
});

test('a picked colour is used when it reads on white and refused with the reason when not', function () {
    $page = Livewire::actingAs(studioCandidate())->test('pages::candidate.cv-builder')
        ->set('template', 'modern')
        ->set('customColour', '#7c2d12')
        ->assertHasNoErrors()
        ->assertSet('accent', '#7C2D12');

    expect($page->instance()->preview)->toContain('#7C2D12');

    $page->set('customColour', '#fef3c7')
        ->assertHasErrors('customColour')
        ->assertSee('too light to read on white paper')
        ->assertSet('accent', '#7C2D12');
});

test('a section dragged to a new place moves there', function () {
    $page = Livewire::actingAs(studioCandidate())->test('pages::candidate.cv-builder')
        ->call('sortSection', 'skills', 0);

    expect($page->instance()->design->order[0])->toBe('skills');

    $page->call('sortSection', 'nonsense', 0);
    expect($page->instance()->design->order[0])->toBe('skills');
});

test('fit to one page steps tighter until there is no step left', function () {
    $page = Livewire::actingAs(studioCandidate())->test('pages::candidate.cv-builder');

    $page->call('tighten')->call('tighten')->call('tighten');

    $page->assertSet('spacing', 'compact')->assertSet('margins', 'narrow')->assertSet('size', 'small');
    expect($page->instance()->tighten())->toBeFalse();
});

test('one entry can be left off this CV without leaving the profile', function () {
    $candidate = studioCandidate();
    $project = $candidate->candidateProfile->projects()->sole();

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->call('toggleEntry', 'projects', $project->id);

    expect($page->instance()->preview)->not->toContain('Shiftboard')
        ->and($candidate->candidateProfile->projects()->count())->toBe(1);

    $page->call('toggleEntry', 'projects', $project->id);
    expect($page->instance()->preview)->toContain('Shiftboard');
});

test('another candidate\'s entry cannot be hidden', function () {
    $other = Project::factory()->create();

    Livewire::actingAs(studioCandidate())->test('pages::candidate.cv-builder')
        ->call('toggleEntry', 'projects', $other->id)
        ->call('toggleEntry', 'nonsense', 1);

    expect(CvVersion::query()->whereNotNull('hidden_entries')->exists())->toBeFalse();
});

test('the Bangladesh preset turns on the photo, personal information, references and a declaration', function () {
    $candidate = studioCandidate();

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->call('applyPreset', 'international');

    expect($page->instance()->hiddenSections)->toContain('personal', 'references', 'declaration');

    $page->call('applyPreset', 'bangladesh')->assertDispatched('cv-extras-changed');

    $version = $candidate->candidateProfile->cvVersions()->sole();
    expect($page->instance()->hiddenSections)->not->toContain('personal')
        ->and($version->cvExtras()->declaration)->toBeTrue()
        ->and($version->cvExtras()->referenceMode)->toBe('on_request');
});

test('the preview reads its own page to draw page breaks, but runs no scripts', function () {
    Livewire::actingAs(studioCandidate())->test('pages::candidate.cv-builder')
        ->assertSeeHtml('sandbox="allow-same-origin"')
        ->assertSee('Fit to one page');
});

test('extras keep only known fields and lengths', function () {
    $extras = CvExtras::fromArray([
        'personal' => ['nid' => '1234567890', 'father_name' => str_repeat('a', 300)],
        'references' => [['name' => ''], ['name' => 'Rina Akter', 'email' => 'rina@example.com'], ['name' => 'B'], ['name' => 'C'], ['name' => 'D']],
        'reference_mode' => 'shout',
        'custom' => [['title' => '', 'items' => []], ['title' => 'Training', 'items' => [['title' => 'x']]]],
    ]);

    expect($extras->personal)->toHaveKey('father_name')->not->toHaveKey('nid')
        ->and(mb_strlen($extras->personal['father_name']))->toBe(100)
        ->and($extras->references)->toHaveCount(3)
        ->and($extras->references[0]['name'])->toBe('Rina Akter')
        ->and($extras->referenceMode)->toBe('none')
        ->and($extras->custom)->toHaveCount(1);
});

test('skills print as a list and the photo round when chosen', function () {
    $candidate = studioCandidate();
    $candidate->candidateProfile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('skillLayout', 'list')
        ->set('photoShape', 'round');

    expect($page->instance()->preview)->toContain('<li>Laravel</li>')->toContain('.photo { border-radius: 50%; }')
        ->and($candidate->candidateProfile->cvVersions()->sole()->design)->toMatchArray(['skills' => 'list', 'photo_shape' => 'round']);
});

test('the apply form points to a CV tailored for the job', function () {
    $candidate = studioCandidate();
    $job = JobPosting::factory()->create();

    Livewire::actingAs($candidate)->test('pages::job-apply', ['jobPosting' => $job])
        ->assertSee('Tailor a CV for this job')
        ->assertSee(route('candidate.cv-builder.tailor', $job));
});
