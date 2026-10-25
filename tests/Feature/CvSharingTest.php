<?php

use App\Models\CvVersion;
use App\Models\ExperienceRecord;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvDocx;
use App\Support\CvPlainText;
use App\Support\CvVersions;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

function sharingCandidate()
{
    $candidate = candidateUser();
    $candidate->forceFill(['name' => 'Nusrat Jahan', 'email' => 'nusrat@example.com'])->save();
    $profile = $candidate->candidateProfile;
    $profile->update(['phone' => '+880 1712-111222', 'location' => 'Feni, Bangladesh', 'headline' => 'Laravel developer', 'github_url' => 'https://github.com/nusrat']);
    ExperienceRecord::factory()->for($profile)->create(['job_title' => 'Backend Developer', 'company_name' => 'Acme', 'description' => '<ul><li>Built the <strong>payments</strong> API</li><li>Cut costs by 20%</li></ul>']);

    return $candidate->fresh();
}

test('sharing makes a private link, and the page shows the CV without contact details', function () {
    $candidate = sharingCandidate();
    $version = CvVersions::open($candidate->candidateProfile);
    $version->update(['extras' => ['personal' => ['father_name' => 'Abdul Karim']]]);

    Livewire::actingAs($candidate)->test('cv.share', ['versionId' => $version->id])
        ->set('published', true)
        ->assertDispatched('cv-updated')
        ->assertSee('Copy link');

    $version->refresh();
    expect($version->public_slug)->toMatch('/^[a-z0-9]{12}$/')->and($version->isPublic())->toBeTrue();

    $this->get(route('cv.public', $version->public_slug))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Nusrat Jahan')
        ->assertSee('Backend Developer')
        ->assertSee('github.com/nusrat')
        ->assertSee('noindex', false)
        ->assertDontSee('nusrat@example.com')
        ->assertDontSee('+880 1712-111222')
        ->assertDontSee('Abdul Karim');
});

test('the owner can choose to show contact details on the page', function () {
    $candidate = sharingCandidate();
    $version = CvVersion::factory()->for($candidate->candidateProfile)->published()->create(['extras' => ['personal' => ['father_name' => 'Abdul Karim']]]);

    Livewire::actingAs($candidate)->test('cv.share', ['versionId' => $version->id])->set('showContact', true);

    $this->get(route('cv.public', $version->public_slug))
        ->assertSee('nusrat@example.com')->assertSee('+880 1712-111222')->assertSee('Abdul Karim');
});

test('a link that is off, run out, replaced or never made answers 404', function () {
    $candidate = sharingCandidate();
    $version = CvVersion::factory()->for($candidate->candidateProfile)->published()->create();
    $slug = $version->public_slug;

    $this->get(route('cv.public', $slug))->assertOk();

    $version->update(['public_expires_at' => now()->subMinute()]);
    $this->get(route('cv.public', $slug))->assertNotFound();

    $version->update(['public_expires_at' => null, 'published_at' => null]);
    $this->get(route('cv.public', $slug))->assertNotFound();

    $version->update(['published_at' => now()]);
    Livewire::actingAs($candidate)->test('cv.share', ['versionId' => $version->id])->call('newLink');
    $this->get(route('cv.public', $slug))->assertNotFound();
    $this->get(route('cv.public', $version->fresh()->public_slug))->assertOk();

    $this->get('/cv/aaaaaaaaaaaa')->assertNotFound();
});

test('an expiry stops sharing on its day', function () {
    $candidate = sharingCandidate();
    $version = CvVersions::open($candidate->candidateProfile);

    Livewire::actingAs($candidate)->test('cv.share', ['versionId' => $version->id])
        ->set('published', true)
        ->set('expires', '7');

    expect($version->fresh()->public_expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

test('a visit is counted once per visitor, never for the owner or a crawler, and leaves "last changed" alone', function () {
    $candidate = sharingCandidate();
    $version = CvVersion::factory()->for($candidate->candidateProfile)->published()->create();
    $updated = $version->updated_at;
    $this->travel(5)->minutes();

    $this->get(route('cv.public', $version->public_slug));
    $this->get(route('cv.public', $version->public_slug));
    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')->flushSession()->get(route('cv.public', $version->public_slug));
    $this->actingAs($candidate)->flushSession()->get(route('cv.public', $version->public_slug));

    expect($version->fresh()->view_count)->toBe(1)
        ->and($version->fresh()->updated_at->equalTo($updated))->toBeTrue();
});

test('the web CV\'s PDF downloads and is counted, with a QR code when asked for', function () {
    $candidate = sharingCandidate();
    $version = CvVersion::factory()->for($candidate->candidateProfile)->published()->create([
        'design' => [...(new CvDesign)->toArray(), 'qr' => true],
    ]);

    $response = $this->get(route('cv.public.pdf', $version->public_slug))->assertOk();
    $pdf = $response->streamedContent();

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_replace('/\s+/', ' ', (new Parser)->parseContent($pdf)->getText()))->toContain($version->publicUrl())
        ->and($version->fresh()->download_count)->toBe(1);
});

test('the builder prints the QR code only while the CV is shared', function () {
    $candidate = sharingCandidate();
    $version = CvVersions::open($candidate->candidateProfile);
    $version->update(['design' => [...$version->design, 'qr' => true]]);

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder');
    expect($page->instance()->preview)->not->toContain('data:image/svg+xml');

    $version->update(['public_slug' => CvVersion::newSlug(), 'published_at' => now()]);
    $page->dispatch('cv-updated');
    expect($page->instance()->preview)->toContain('data:image/svg+xml')->toContain($version->publicUrl());
});

test('the Word file is a real document with every section in order', function () {
    $candidate = sharingCandidate();
    $version = CvVersions::open($candidate->candidateProfile);
    $version->update(['extras' => ['interests' => 'Chess & football', 'declaration' => true]]);
    $cv = CvData::fromProfile($candidate->candidateProfile->fresh(), $version);

    $path = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($path, CvDocx::for($cv, CvDesign::from('modern', 'teal'), 'letter'));
    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('Nusrat Jahan')
        ->toContain('<w:b/></w:rPr><w:t xml:space="preserve">payments</w:t>')
        ->toContain('<w:numId w:val="1"/>')
        ->toContain('Chess &amp; football')
        ->toContain('w:w="12240"');

    expect(strpos($xml, 'Work Experience'))->toBeLessThan(strpos($xml, 'Interests'));
});

test('the text version reads in the same order, with bullets as dashes', function () {
    $cv = CvData::fromProfile(sharingCandidate()->candidateProfile);
    $text = CvPlainText::for($cv, new CvDesign);

    expect($text)->toStartWith('Nusrat Jahan')
        ->toContain("WORK EXPERIENCE\n\nBackend Developer · Acme")
        ->toContain('- Built the payments API');
});

test('the builder downloads the Word and text versions', function () {
    Livewire::actingAs(sharingCandidate())->test('pages::candidate.cv-builder')
        ->call('downloadWord')->assertFileDownloaded('Nusrat-Jahan-CV.docx')
        ->call('downloadText')->assertFileDownloaded('Nusrat-Jahan-CV.txt');
});
