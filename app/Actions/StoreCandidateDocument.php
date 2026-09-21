<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Support\DocumentUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Put a new file into a candidate's document library.
 *
 * Adding a CV from the documents page and uploading a new one while
 * applying are the same act, so they share this one path -- including
 * the rule that only the most recent CVs are kept. The ones that drop off
 * are soft-deleted, which takes them out of the library without touching
 * any application that was already sent with them.
 */
class StoreCandidateDocument
{
    public function __invoke(CandidateProfile $candidateProfile, DocumentType $type, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($candidateProfile, $type, $file) {
            $document = $candidateProfile->documents()->create([
                'document_type' => $type,
                'file_path' => $file->store('documents', 'local'),
                'original_filename' => $file->getClientOriginalName(),
            ]);

            if ($type === DocumentType::Cv) {
                $candidateProfile->documents()
                    ->where('document_type', DocumentType::Cv)
                    ->latest()
                    ->latest('id')
                    ->get()
                    ->slice(DocumentUploads::RECENT_CVS_KEPT)
                    ->each->delete();
            }

            return $document;
        });
    }
}
