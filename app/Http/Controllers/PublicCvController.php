<?php

namespace App\Http\Controllers;

use App\Actions\RenderCvPdf;
use App\Exceptions\CvPdfUnavailable;
use App\Models\CvVersion;
use App\Support\CvData;
use App\Support\CvQr;
use App\Support\CvTailoring;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Spatie\LaravelPdf\Enums\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A CV its owner shares as a web page (see CvVersion): the CV itself, in
 * its own template, with a bar to download the PDF. Anyone with the link
 * may read it; search engines are asked not to list it or follow it.
 * Contact details stay off unless the owner allows them. A link that was
 * never shared, was turned off or has run out answers 404, the same as
 * one that never existed.
 *
 * Views are counted once per visitor session, never for the owner or a
 * crawler; downloads every time. Neither moves the CV's "last changed".
 */
class PublicCvController extends Controller
{
    private const HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'private, no-store',
    ];

    public function show(Request $request, CrawlerDetect $crawlers, string $slug): Response
    {
        $version = $this->shared($slug);
        $owner = $request->user()?->candidateProfile?->id === $version->candidate_profile_id;
        $seenKey = 'cv-viewed.'.$version->id;

        if (! $owner && ! $request->session()->has($seenKey) && ! $crawlers->isCrawler((string) $request->userAgent())) {
            $request->session()->put($seenKey, true);
            DB::table('cv_versions')->where('id', $version->id)->increment('view_count');
        }

        $design = $version->cvDesign();
        $cv = $this->cv($version);

        $html = view($design->template->view(), [
            'cv' => $cv,
            'photo' => ($version->design['photo'] ?? false) ? $cv->photo() : null,
            'design' => $design,
            'preview' => true,
            'fonts' => 'url',
        ])->render();

        // The page around the CV: a robots tag, a phone-width viewport, a
        // grey desk for the sheet to lie on, and a bar to download it.
        $html = str_replace('</head>', view('cv.public.head')->render().'</head>', $html);
        $bar = view('cv.public.bar', ['version' => $version, 'cv' => $cv])->render();
        $html = preg_replace_callback('/<body[^>]*>/', fn (array $match) => $match[0].$bar, $html, 1) ?? $html;

        return response($html, 200, self::HEADERS);
    }

    public function pdf(string $slug): StreamedResponse|Response
    {
        $version = $this->shared($slug);
        $design = $version->cvDesign();
        $cv = $this->cv($version);

        if ($design->qr) {
            $cv = $cv->withQr(CvQr::for($version->publicUrl()), $version->publicUrl());
        }

        try {
            $pdf = app(RenderCvPdf::class)($cv, (bool) ($version->design['photo'] ?? false), ($version->design['paper'] ?? 'a4') === 'letter' ? Format::Letter : Format::A4, $design);
        } catch (CvPdfUnavailable) {
            return response(__('The PDF could not be made just now. Please try again in a few minutes.'), 503, self::HEADERS);
        } catch (Throwable $exception) {
            report($exception);

            return response(__('The PDF could not be made just now. Please try again in a few minutes.'), 503, self::HEADERS);
        }

        DB::table('cv_versions')->where('id', $version->id)->increment('download_count');

        return response()->streamDownload(fn () => print ($pdf), $cv->fileName(), [...self::HEADERS, 'Content-Type' => 'application/pdf']);
    }

    private function shared(string $slug): CvVersion
    {
        $version = CvVersion::query()->with('candidateProfile.user', 'jobPosting')->where('public_slug', $slug)->first();

        abort_unless($version?->isPublic() && $version->candidateProfile->user && ! $version->candidateProfile->user->trashed(), 404);

        return $version;
    }

    private function cv(CvVersion $version): CvData
    {
        return CvData::fromProfile($version->candidateProfile, $version, CvTailoring::for($version)->keywords, public: true);
    }
}
