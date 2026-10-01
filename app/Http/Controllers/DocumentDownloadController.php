<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * A thin single-action controller: a policy-gated download endpoint so a
 * candidate's CV or document can never be fetched by guessing its storage
 * path. The file itself lives on the `local` (private) disk, never
 * `public`, because a file on the public disk is served to anyone who
 * knows its URL.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document)
    {
        $this->authorize('download', $document);

        // A file erased with its owner's data leaves its row behind for the
        // application's record; it answers as gone rather than failing.
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
