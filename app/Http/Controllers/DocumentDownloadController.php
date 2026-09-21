<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Single-action controller (claude/15's "thin Controller" convention) --
 * this route exists purely as the claude/14 step-7 security fix: a
 * Policy-gated download endpoint so a candidate's CV/document can never be
 * fetched by guessing its storage path. The file itself lives on the
 * `local` (private) disk, never `public` -- see 04_roadmap.md's storage
 * disk decision.
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
