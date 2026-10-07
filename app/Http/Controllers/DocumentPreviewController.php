<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Shows one of the candidate's own documents in the browser instead of
 * downloading it, as Indeed's "Preview" does. Only the kinds a browser
 * draws by itself are offered -- PDF and the two photo formats; a Word
 * file or an archive is downloaded to be opened.
 *
 * Same owner check and private disk as the download; the type is taken
 * from our own list rather than from the file, and the browser is told
 * not to guess another.
 */
class DocumentPreviewController extends Controller
{
    public const TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public static function canPreview(Document $document): bool
    {
        return array_key_exists(self::extension($document), self::TYPES);
    }

    /**
     * The type the file is served as, from our own list; null for a kind
     * that is downloaded instead.
     */
    public static function contentType(Document $document): ?string
    {
        return self::TYPES[self::extension($document)] ?? null;
    }

    public static function isImage(Document $document): bool
    {
        return str_starts_with(self::TYPES[self::extension($document)] ?? '', 'image/');
    }

    public function __invoke(Document $document)
    {
        $this->authorize('download', $document);

        abort_unless(self::canPreview($document), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->original_filename, [
            'Content-Type' => self::TYPES[self::extension($document)],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private static function extension(Document $document): string
    {
        return strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
    }
}
