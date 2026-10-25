<?php

namespace App\Actions;

use App\Exceptions\CvPdfUnavailable;
use App\Support\CvData;
use App\Support\CvDesign;
use InvalidArgumentException;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

/**
 * Turn a CV into PDF bytes.
 *
 * With the browser engine on (config cv.pdf_engine), Chromium draws the
 * CV in Cloudflare with its own typeface, Bengali included, as a tagged
 * PDF. If that fails -- Cloudflare unreachable, the day's free browser
 * time used up -- a CV in Latin script is drawn with DOMPDF on our own
 * server instead, and only a CV DOMPDF cannot draw says to try again.
 *
 * Only A4 and US Letter are offered: a CV is A4 in most of the world and
 * Letter in North America. Nothing is cached, since a CV is personal and
 * is built again from the profile every time.
 */
class RenderCvPdf
{
    public const PAPERS = [Format::A4, Format::Letter];

    public function __invoke(CvData $cv, bool $withPhoto = false, Format $paper = Format::A4, ?CvDesign $design = null, ?string $view = null, array $data = []): string
    {
        throw_unless(in_array($paper, self::PAPERS, true), InvalidArgumentException::class, "A CV is printed on A4 or Letter, not {$paper->value}.");

        $design ??= new CvDesign;
        $data = [...$data, 'cv' => $cv, 'photo' => $withPhoto ? $cv->photo() : null, 'design' => $design];
        $view ??= $design->template->view();

        if (self::usesBrowser()) {
            try {
                return $this->draw($view, [...$data, 'fonts' => 'embed'], $paper, 'cloudflare');
            } catch (Throwable $exception) {
                report($exception);

                if ($cv->unsupportedScriptParts() !== []) {
                    throw new CvPdfUnavailable('The browser engine failed and DOMPDF cannot draw this script.', previous: $exception);
                }
            }
        }

        return $this->draw($view, [...$data, 'fonts' => 'none'], $paper, 'dompdf');
    }

    /**
     * Whether PDFs are drawn by Chromium in Cloudflare: switched on, and
     * the account set.
     */
    public static function usesBrowser(): bool
    {
        return config('cv.pdf_engine') === 'cloudflare'
            && filled(config('laravel-pdf.cloudflare.api_token'))
            && filled(config('laravel-pdf.cloudflare.account_id'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function draw(string $view, array $data, Format $paper, string $driver): string
    {
        $pdf = Pdf::view($view, $data)
            ->driver($driver)
            ->format($paper)
            ->dontCache();

        if ($driver === 'cloudflare') {
            $pdf->tagged();
        }

        return $pdf->generatePdfContent();
    }
}
