<?php

use App\Actions\RenderCvPdf;
use App\Enums\CvFont;
use App\Exceptions\CvPdfUnavailable;
use App\Models\ExperienceRecord;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvFonts;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A profile with one role, in Latin script unless told otherwise.
 */
function engineCv(?string $name = null): CvData
{
    $candidate = candidateUser();

    if ($name !== null) {
        $candidate->forceFill(['name' => $name])->save();
    }

    ExperienceRecord::factory()->for($candidate->candidateProfile)->create(['job_title' => 'Laravel Developer', 'company_name' => 'Acme']);

    return CvData::fromProfile($candidate->fresh()->candidateProfile);
}

function useCloudflare(): void
{
    config([
        'cv.pdf_engine' => 'cloudflare',
        'laravel-pdf.cloudflare.api_token' => 'test-token',
        'laravel-pdf.cloudflare.account_id' => 'test-account',
    ]);
}

test('the shipped fonts are served to any origin, and nothing else is', function () {
    $file = CvFont::Inter->files()[0]['file'];

    $this->get(route('cv-fonts', $file))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Content-Type', 'font/woff2');

    $this->get('/cv-fonts/not-a-font.woff2')->assertNotFound();
    $this->get('/cv-fonts/..%2F..%2F.env')->assertNotFound();
});

test('every font file a typeface lists ships with the app', function (CvFont $font) {
    foreach ($font->files() as $file) {
        expect(file_exists(CvFont::path($file['file'])))->toBeTrue($file['file']);
    }
})->with(CvFont::cases());

test('font rules are links for the preview, data for the browser PDF, and nothing for DOMPDF', function () {
    $url = CvFonts::css(CvFont::Lato, 'url');
    $embed = CvFonts::css(CvFont::Lato, 'embed', withBengali: false);

    expect($url)->toContain(route('cv-fonts', 'lato-latin-400.woff2'))->toContain("font-family: 'Noto Sans Bengali'")
        ->and($embed)->toContain('data:font/woff2;base64,')->not->toContain('Noto Sans Bengali')
        ->and(CvFonts::css(CvFont::Lato, 'none'))->toBe('')
        ->and(CvFont::Merriweather->stack())->toStartWith("'Merriweather', 'Noto Serif Bengali', 'DejaVu Serif'");
});

test('without Cloudflare set up, CVs are drawn on our own server', function () {
    Http::preventStrayRequests();
    config(['cv.pdf_engine' => 'cloudflare', 'laravel-pdf.cloudflare.api_token' => null]);

    expect(RenderCvPdf::usesBrowser())->toBeFalse()
        ->and(app(RenderCvPdf::class)(engineCv()))->toStartWith('%PDF');
});

test('with Cloudflare on, the CV goes there as a tagged PDF with its fonts inside', function () {
    useCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.7 from chromium', 200)]);

    $pdf = app(RenderCvPdf::class)(engineCv('করিম রহমান'), design: CvDesign::from('modern', 'teal', type: ['font' => 'inter']));

    expect($pdf)->toBe('%PDF-1.7 from chromium');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/accounts/test-account/browser-rendering/pdf')
            && $request['pdfOptions']['tagged'] === true
            && str_contains($request['html'], "font-family: 'Inter'")
            && str_contains($request['html'], "font-family: 'Noto Sans Bengali'")
            && str_contains($request['html'], 'data:font/woff2;base64,')
            && str_contains($request['html'], 'করিম রহমান');
    });
});

test('when Cloudflare fails, a Latin CV is drawn on our own server instead', function () {
    useCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response('quota exceeded', 429)]);

    expect(app(RenderCvPdf::class)(engineCv()))->toStartWith('%PDF');
});

test('when Cloudflare fails, a Bengali CV is not drawn with broken letters', function () {
    useCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response('down', 500)]);

    app(RenderCvPdf::class)(engineCv('করিম রহমান'));
})->throws(CvPdfUnavailable::class);

test('the builder says to try again when only Cloudflare could draw the CV', function () {
    useCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response('down', 500)]);
    $candidate = candidateUser();
    $candidate->forceFill(['name' => 'করিম রহমান'])->save();
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create();

    Livewire\Livewire::actingAs($candidate->fresh())->test('pages::candidate.cv-builder')
        ->call('download')
        ->assertHasErrors('build')
        ->assertSee('only our PDF service can draw');
});

test('with the browser engine Bengali is not warned about, other scripts still are', function () {
    $candidate = candidateUser();
    $candidate->forceFill(['name' => 'করিম রহমান'])->save();
    $candidate->candidateProfile->update(['headline' => 'مطور']);
    $cv = CvData::fromProfile($candidate->fresh()->candidateProfile);

    expect($cv->unsupportedScriptParts())->toBe(['Name', 'Headline'])
        ->and($cv->unsupportedScriptParts(browserEngine: true))->toBe(['Headline'])
        ->and($cv->hasBengali())->toBeTrue();
});
