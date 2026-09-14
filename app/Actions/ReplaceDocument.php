<?php

namespace App\Actions;

use App\Models\CandidateProfile;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * "Replace" a document in the candidate's library -- swap the file behind
 * a slot (CV, work sample, certificate) while keeping every past
 * Application's resume_document_id pointing at an intact snapshot.
 *
 * claude/13's rule: deleting/replacing from the library must never change
 * what an already-submitted Application shows. Updating the existing
 * Document row in place would break that (every old Application would
 * suddenly point at the new file). So this creates a brand-new Document
 * row for the new file, then soft-deletes the old one -- the old row (and
 * every Application snapshot referencing it) stays exactly as it was.
 */
class ReplaceDocument
{
    public function __invoke(CandidateProfile $candidateProfile, Document $old, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($candidateProfile, $old, $file) {
            $path = $file->store('documents', 'local');

            $new = $candidateProfile->documents()->create([
                'document_type' => $old->document_type,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
            ]);

            $old->delete();

            return $new;
        });
    }
}
