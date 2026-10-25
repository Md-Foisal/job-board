<?php

namespace App\Support;

use App\Enums\CvFont;

/**
 * The @font-face rules for a CV's typeface, in the form each way of
 * drawing it can use:
 *
 * - url: the preview in the builder, the public web CV. The browser
 *   fetches only the files whose Unicode range the page uses, from our
 *   own font route (which allows any origin, since the preview frame is
 *   sandboxed and has no origin of its own).
 * - embed: a PDF drawn by Chromium in Cloudflare. The files travel inside
 *   the page as data URIs, so the render never depends on reaching our
 *   site (a local machine is not reachable at all). The Bengali files go
 *   along only when the CV has Bengali in it.
 * - none: DOMPDF, which reads no web fonts and draws with DejaVu.
 */
final class CvFonts
{
    public const MODES = ['url', 'embed', 'none'];

    public static function css(CvFont $font, string $mode, bool $withBengali = true): string
    {
        if ($mode === 'none') {
            return '';
        }

        return collect($font->files())
            ->filter(fn (array $file) => $withBengali || $file['subset'] !== 'bengali')
            ->map(fn (array $file) => sprintf(
                "@font-face { font-family: '%s'; font-style: normal; font-weight: %s; font-display: block; src: url(%s) format('woff2'); unicode-range: %s; }",
                $file['family'],
                $file['weight'],
                $mode === 'embed' ? self::dataUri($file['file']) : route('cv-fonts', $file['file']),
                CvFont::RANGES[$file['subset']],
            ))
            ->implode("\n");
    }

    private static function dataUri(string $file): string
    {
        return 'data:font/woff2;base64,'.base64_encode((string) file_get_contents(CvFont::path($file)));
    }
}
