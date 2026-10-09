<?php

namespace App\Actions;

use App\Support\CvData;
use App\Support\CvDesign;
use InvalidArgumentException;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Turn a CV into PDF bytes, on our own server, with whichever PDF driver
 * the app is configured for.
 *
 * Only A4 and US Letter are offered: a CV is A4 in most of the world and
 * Letter in North America. Nothing is cached, since a CV is personal and
 * is built again from the profile every time.
 */
class RenderCvPdf
{
    public const PAPERS = [Format::A4, Format::Letter];

    public function __invoke(CvData $cv, bool $withPhoto = false, Format $paper = Format::A4, ?CvDesign $design = null): string
    {
        throw_unless(in_array($paper, self::PAPERS, true), InvalidArgumentException::class, "A CV is printed on A4 or Letter, not {$paper->value}.");

        $design ??= new CvDesign;

        return Pdf::view($design->template->view(), ['cv' => $cv, 'photo' => $withPhoto ? $cv->photo() : null, 'design' => $design])
            ->format($paper)
            ->dontCache()
            ->generatePdfContent();
    }
}
