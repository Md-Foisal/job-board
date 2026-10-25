<?php

namespace App\Http\Controllers;

use App\Enums\CvFont;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the fonts a CV is drawn with to the CV preview and the public
 * web CV. Any origin may read them: the preview is a sandboxed frame
 * with no origin of its own, and a browser only lets such a page use a
 * font that says so. Only the shipped font files are served.
 */
class CvFontController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(CvFont::ships($file), 404);

        return response()->file(CvFont::path($file), [
            'Content-Type' => 'font/woff2',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
