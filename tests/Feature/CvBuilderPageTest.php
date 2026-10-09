<?php

use App\Actions\RenderCvPdf;
use App\Enums\DocumentType;
use App\Models\CandidatePreference;
use App\Models\Document;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * Rahim: a headline, one role and one skill -- enough for a CV -- and a
 * salary preference that must never appear on it.
 */
function rahim(): User
{
    $rahim = candidateUser()->fresh();
    $rahim->forceFill(['name' => 'Rahim Uddin', 'email' => 'rahim@example.com'])->save();

    $profile = $rahim->candidateProfile;
    $profile->update(['headline' => 'Frontend developer', 'bio' => 'I build interfaces.', 'phone' => '+880 1812-000111', 'location' => 'Chattogram, Bangladesh']);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Vue Developer', 'company_name' => 'Delta Ltd', 'start_date' => '2022-05-01', 'end_date' => null, 'description' => '<p>Built the dashboard.</p>',
    ]);
    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Vue.js']), ['proficiency' => 'advanced']);
    CandidatePreference::factory()->for($profile)->create(['desired_salary_min' => 87654, 'desired_salary_currency' => 'BDT']);

    return $rahim->fresh();
}

function builderPage(User $candidate)
{
    return Livewire::actingAs($candidate)->test('pages::candidate.cv-builder');
}

function cvs(User $candidate)
{
    return $candidate->candidateProfile->documents()->where('document_type', DocumentType::Cv);
}

test('only a signed-in candidate can open the CV builder', function () {
    $this->get(route('candidate.cv-builder'))->assertRedirect(route('login'));

    $this->actingAs(employerUser())->get(route('candidate.cv-builder'))->assertForbidden();

    $this->actingAs(rahim())->get(route('candidate.cv-builder'))->assertOk()->assertSee('CV builder');
});

test('the preview shows the CV from the profile, and never the salary preference', function () {
    $page = builderPage(rahim())
        ->assertSee('Save to my CVs')
        ->assertSee('Download PDF');

    $preview = $page->instance()->preview;

    expect($preview)->toContain('Rahim Uddin')
        ->toContain('+880 1812-000111')
        ->toContain('Vue Developer')
        ->toContain('class="preview"')
        ->not->toContain('87654')
        ->not->toContain('87,654');
});

test('what the CV is missing is listed with a link to add it', function () {
    $candidate = rahim();
    $candidate->candidateProfile->update(['phone' => null]);

    builderPage($candidate)
        ->assertSee('No phone number, so an employer can only reach you by email.')
        ->assertSee('No education.')
        ->assertSee(route('candidate.education.index'))
        ->assertDontSee('Nothing missing');
});

test('a profile with nothing to put on a CV gets the editor and an empty page, not a CV', function () {
    $candidate = candidateUser();

    builderPage($candidate)
        ->assertSee('Your CV appears here')
        ->assertDontSee('Save to my CVs')
        ->call('save')
        ->call('download')
        ->assertNoFileDownloaded();

    expect(cvs($candidate)->count())->toBe(0);
});

test('saving puts a real PDF into the library and offers it for download', function () {
    $candidate = rahim();

    builderPage($candidate)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Saved to your CVs as Rahim-Uddin-CV.pdf.');

    $document = cvs($candidate)->sole();
    $pdf = Storage::disk('local')->get($document->file_path);

    expect($document->original_filename)->toBe('Rahim-Uddin-CV.pdf')
        ->and($pdf)->toStartWith('%PDF-')
        ->and((new Parser)->parseContent($pdf)->getText())->toContain('Vue Developer');
});

test('each save is a new document, and the page warns which CV the next one pushes out', function () {
    $candidate = rahim();
    $oldest = null;

    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        $document = Document::factory()->for($candidate->candidateProfile)->create(['document_type' => DocumentType::Cv, 'original_filename' => "cv-{$i}.pdf"]);
        $oldest ??= $document;
    }

    $this->travel(1)->minutes();

    builderPage($candidate)
        ->assertSee('saving moves your oldest, cv-1.pdf, out of your library')
        ->call('save');

    expect($oldest->fresh()->trashed())->toBeTrue()
        ->and(cvs($candidate)->count())->toBe(4)
        ->and(cvs($candidate)->latest('id')->first()->original_filename)->toBe('Rahim-Uddin-CV.pdf');
});

test('downloading gives the PDF without adding it to the library', function () {
    $candidate = rahim();

    builderPage($candidate)
        ->call('download')
        ->assertFileDownloaded('Rahim-Uddin-CV.pdf');

    expect(Document::count())->toBe(0);
});

test('Letter paper is used when chosen, and any other paper is refused', function () {
    $candidate = rahim();

    builderPage($candidate)
        ->set('paper', 'letter')
        ->call('save')
        ->set('paper', 'a3')
        ->call('save')
        ->assertHasErrors('paper');

    $pdf = Storage::disk('local')->get(cvs($candidate)->sole()->file_path);
    $mediaBox = (new Parser)->parseContent($pdf)->getPages()[0]->getDetails()['MediaBox'];

    expect(array_map('round', $mediaBox))->toBe([0.0, 0.0, 612.0, 792.0]);
});

test('the photo option appears only with a photo, starts off, and adds it when switched on', function () {
    $candidate = rahim();

    builderPage($candidate)->assertDontSee('Include my photo');

    $image = imagecreatetruecolor(300, 300);
    ob_start();
    imagejpeg($image);
    Storage::disk('public')->put('avatars/rahim.jpg', ob_get_clean());
    $candidate->forceFill(['avatar' => 'avatars/rahim.jpg'])->save();

    $page = builderPage($candidate->fresh())
        ->assertSee('Include my photo')
        ->assertSet('withPhoto', false);

    expect($page->instance()->preview)->not->toContain('<img');

    $page->set('withPhoto', true);

    expect($page->instance()->preview)->toContain('src="data:image/jpeg;base64,');
});

test('text the PDF cannot draw is warned about, naming the parts', function () {
    $candidate = rahim();
    $candidate->forceFill(['name' => 'রহিম উদ্দিন'])->save();
    $candidate->candidateProfile->update(['headline' => 'ফ্রন্টএন্ড ডেভেলপার']);

    builderPage($candidate->fresh())
        ->assertSee('Some text may not show correctly in the PDF')
        ->assertSee('Text in your name and headline is in a script');
});

test('a CV in Latin script gets no warning', function () {
    builderPage(rahim())->assertDontSee('Some text may not show correctly in the PDF');
});

test('saving and downloading share an hourly limit', function () {
    $candidate = rahim();
    $page = builderPage($candidate);

    foreach (range(1, 10) as $ignored) {
        $page->call('download');
    }

    $page->call('save')
        ->assertHasErrors('build')
        ->assertSee("You've built 10 CVs in the last hour.");

    expect(Document::count())->toBe(0);

    RateLimiter::clear('cv-build:'.$candidate->id);

    $page->call('save')
        ->assertHasNoErrors()
        ->assertDontSee("You've built 10 CVs in the last hour.");

    expect(Document::count())->toBe(1);
});

test('a PDF that fails to build saves nothing and says so', function () {
    $candidate = rahim();
    $this->mock(RenderCvPdf::class)->shouldReceive('__invoke')->andThrow(new RuntimeException('Font cache not writable'));

    builderPage($candidate)
        ->call('save')
        ->assertSet('buildFailed', true)
        ->assertSee("We couldn't build the PDF. Nothing was saved; please try again.")
        ->call('download')
        ->assertNoFileDownloaded();

    expect(Document::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('changing an option clears the last saved notice', function () {
    builderPage(rahim())
        ->call('save')
        ->assertSee('Saved to your CVs')
        ->set('paper', 'letter')
        ->assertSet('savedDocumentId', null)
        ->assertDontSee('Saved to your CVs');
});

test('the CV builder is linked from the sidebar, the documents page and the profile', function () {
    $candidate = rahim();

    $this->actingAs($candidate)->get(route('candidate.documents.index'))
        ->assertSee('Build a CV from your profile')
        ->assertSee(route('candidate.cv-builder'));

    $this->actingAs($candidate)->get(route('candidate.profile.edit'))
        ->assertSee('Build a CV')
        ->assertSee(route('candidate.cv-builder'));
});
