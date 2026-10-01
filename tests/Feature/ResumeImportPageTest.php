<?php

use App\Enums\DocumentType;
use App\Enums\MembershipRole;
use App\Enums\ProficiencyLevel;
use App\Models\Document;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CvFiles;

beforeEach(function () {
    Storage::fake('local');

    foreach (['Laravel', 'PHP', 'Docker'] as $name) {
        Skill::create(['name' => $name]);
    }
});

/**
 * A candidate whose profile has no links yet: the factory fills them in
 * at random, and these tests need to know what is already there.
 */
function candidateWithoutLinks(): User
{
    $candidate = candidateUser();
    $candidate->candidateProfile->update(['linkedin_url' => null, 'github_url' => null, 'portfolio_url' => null]);

    return $candidate;
}

function karimsCv(User $candidate, string $extension = 'pdf'): Document
{
    $bytes = $extension === 'pdf'
        ? CvFiles::pdf([['Karim Rahman', 'Laravel and PHP developer']], ['https://www.linkedin.com/in/karim-rahman', 'https://github.com/karim'])
        : CvFiles::docx(['Karim Rahman', 'Laravel and PHP developer'], hyperlinks: ['https://www.linkedin.com/in/karim-rahman', 'https://github.com/karim']);

    return CvFiles::stored($extension, $bytes, ['candidate_profile_id' => $candidate->candidateProfile->id]);
}

function importPage(User $candidate, Document $document)
{
    return Livewire::actingAs($candidate)->test('pages::candidate.resume-import', ['document' => $document]);
}

test('the page shows what the CV suggests, all ticked when the profile is empty', function (string $extension) {
    $candidate = candidateWithoutLinks();
    $document = karimsCv($candidate, $extension);

    $this->actingAs($candidate)->get(route('candidate.resume-import', $document))
        ->assertOk()
        ->assertSee('Fill your profile from your CV')
        ->assertSee('found the things below')
        ->assertDontSee('Everything this CV suggests is already on your profile.')
        ->assertSee('Laravel')
        ->assertSee('https://www.linkedin.com/in/karim-rahman')
        ->assertDontSee('Docker')
        ->assertSee('We pick out skills and profile links. Add your work history and education yourself');

    importPage($candidate, $document)
        ->assertSet('chosenSkills', Skill::whereIn('name', ['Laravel', 'PHP'])->orderBy('name')->pluck('id')->all())
        ->assertSet('chosenLinks', ['linkedin_url', 'github_url']);
})->with(['pdf', 'docx']);

test('adding writes only what is ticked', function () {
    $candidate = candidateWithoutLinks();
    $document = karimsCv($candidate);
    $php = Skill::where('name', 'PHP')->first();

    importPage($candidate, $document)
        ->set('chosenSkills', [$php->id])
        ->set('chosenLinks', ['github_url'])
        ->call('import')
        ->assertHasNoErrors();

    $profile = $candidate->candidateProfile->refresh();

    expect(skillNames($profile->skills))->toBe(['PHP'])
        ->and($profile->github_url)->toBe('https://github.com/karim')
        ->and($profile->linkedin_url)->toBeNull();
});

test('after adding, the added things read as already on the profile and nothing is ticked', function () {
    $candidate = candidateWithoutLinks();
    $document = karimsCv($candidate);

    importPage($candidate, $document)
        ->call('import')
        ->assertSet('chosenSkills', [])
        ->assertSet('chosenLinks', [])
        ->assertSee('already on your profile')
        ->assertSee('Everything this CV suggests is already on your profile.')
        ->assertDontSee('Add to my profile');

    expect($candidate->candidateProfile->skills()->count())->toBe(2);
});

test('a CV whose every suggestion is already on the profile says so when the page opens', function () {
    $candidate = candidateWithoutLinks();
    $candidate->candidateProfile->update([
        'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
        'github_url' => 'https://github.com/karim',
    ]);
    $candidate->candidateProfile->skills()->attach(Skill::whereIn('name', ['Laravel', 'PHP'])->pluck('id'));

    $this->actingAs($candidate)->get(route('candidate.resume-import', karimsCv($candidate)))
        ->assertOk()
        ->assertSee('Everything this CV suggests is already on your profile.')
        ->assertDontSee('Add to my profile');
});

test('a different link already on the profile is offered as a replacement, unticked', function () {
    $candidate = candidateWithoutLinks();
    $candidate->candidateProfile->update(['github_url' => 'https://github.com/karim-old']);
    $document = karimsCv($candidate);

    $page = importPage($candidate, $document)
        ->assertSet('chosenLinks', ['linkedin_url'])
        ->assertSee('Replace your GitHub link')
        ->call('import');

    expect($candidate->candidateProfile->refresh()->github_url)->toBe('https://github.com/karim-old');

    $page->set('chosenLinks', ['github_url'])->call('import');

    expect($candidate->candidateProfile->refresh()->github_url)->toBe('https://github.com/karim');
});

test('the same address written differently counts as already on the profile', function () {
    $candidate = candidateWithoutLinks();
    $candidate->candidateProfile->update(['linkedin_url' => 'http://linkedin.com/in/karim-rahman/']);

    importPage($candidate, karimsCv($candidate))
        ->assertSet('chosenLinks', ['github_url'])
        ->assertDontSee('Replace your LinkedIn link');
});

test('a skill already held is shown as such and keeps its level', function () {
    $candidate = candidateWithoutLinks();
    $laravel = Skill::where('name', 'Laravel')->first();
    $candidate->candidateProfile->skills()->attach($laravel->id, ['proficiency' => ProficiencyLevel::Advanced->value]);

    importPage($candidate, karimsCv($candidate))
        ->assertSet('chosenSkills', [Skill::where('name', 'PHP')->value('id')])
        ->call('import');

    expect($candidate->candidateProfile->skills()->find($laravel->id)->pivot->proficiency)->toBe(ProficiencyLevel::Advanced);
});

test('ticking nothing adds nothing and says so', function () {
    $candidate = candidateWithoutLinks();

    importPage($candidate, karimsCv($candidate))
        ->set('chosenSkills', [])
        ->set('chosenLinks', [])
        ->call('import');

    expect($candidate->candidateProfile->skills()->count())->toBe(0)
        ->and($candidate->candidateProfile->refresh()->linkedin_url)->toBeNull();
});

test('only suggested items can be added, whatever the browser sends', function () {
    $candidate = candidateWithoutLinks();
    $docker = Skill::where('name', 'Docker')->first();

    $page = importPage($candidate, karimsCv($candidate))
        ->set('chosenSkills', [$docker->id])
        ->set('chosenLinks', ['portfolio_url', 'headline'])
        ->call('import');

    expect($candidate->candidateProfile->skills()->count())->toBe(0);

    expect(fn () => $page->set('draft.links.portfolio_url', 'https://evil.example'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a CV with no readable text says so and points to filling in by hand', function () {
    $candidate = candidateWithoutLinks();
    $document = CvFiles::stored('pdf', CvFiles::pdf([[]]), ['candidate_profile_id' => $candidate->candidateProfile->id]);

    $this->actingAs($candidate)->get(route('candidate.resume-import', $document))
        ->assertOk()
        ->assertSee("We couldn't read any text in this CV")
        ->assertDontSee('Add to my profile')
        ->assertDontSee('found the things below');
});

test('a CV with text but nothing on our lists says what was looked for', function () {
    $candidate = candidateWithoutLinks();
    $document = CvFiles::stored('pdf', CvFiles::pdf([['Karim Rahman', 'Pastry chef']]), ['candidate_profile_id' => $candidate->candidateProfile->id]);

    $this->actingAs($candidate)->get(route('candidate.resume-import', $document))
        ->assertOk()
        ->assertSee('No skills or profile links found')
        ->assertDontSee('Add to my profile')
        ->assertDontSee('found the things below');
});

test('only the candidate\'s own, current, readable CV has this page', function () {
    $candidate = candidateWithoutLinks();
    $profileId = $candidate->candidateProfile->id;

    $someoneElses = karimsCv(candidateUser());
    $certificate = CvFiles::stored('pdf', CvFiles::pdf([['Certificate']]), ['candidate_profile_id' => $profileId, 'document_type' => DocumentType::Certificate]);
    $wordDoc = CvFiles::stored('doc', 'binary word 97 content', ['candidate_profile_id' => $profileId]);
    $rotatedOut = karimsCv($candidate);
    $rotatedOut->delete();

    foreach ([$someoneElses, $certificate, $wordDoc, $rotatedOut] as $document) {
        $this->actingAs($candidate)->get(route('candidate.resume-import', $document))->assertNotFound();
    }
});

test('guests are sent to log in, and employers cannot use the page', function () {
    $candidate = candidateWithoutLinks();
    $document = karimsCv($candidate);

    $this->get(route('candidate.resume-import', $document))->assertRedirect(route('login'));

    $this->actingAs(employerUser(role: MembershipRole::Owner))
        ->get(route('candidate.resume-import', $document))
        ->assertForbidden();
});

test('the documents page offers the import next to PDF and Word CVs only', function () {
    $candidate = candidateWithoutLinks();
    $profileId = $candidate->candidateProfile->id;
    $pdf = karimsCv($candidate);
    $wordDoc = CvFiles::stored('doc', 'binary word 97 content', ['candidate_profile_id' => $profileId, 'original_filename' => 'old.doc']);
    $certificate = CvFiles::stored('pdf', CvFiles::pdf([['Certificate']]), ['candidate_profile_id' => $profileId, 'document_type' => DocumentType::Certificate]);

    $this->actingAs($candidate)->get(route('candidate.documents.index'))
        ->assertOk()
        ->assertSee(route('candidate.resume-import', $pdf))
        ->assertDontSee(route('candidate.resume-import', $wordDoc))
        ->assertDontSee(route('candidate.resume-import', $certificate))
        ->assertSee('Upload this CV as PDF or DOCX to fill your profile from it.');
});
