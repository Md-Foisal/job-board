<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Support\DocumentUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Put a new file into a candidate's document library.
 *
 * Adding a CV from the documents page, uploading a new one while applying
 * and saving one built from the profile are the same act, so they share
 * this one path -- including the rule that only the most recent CVs are
 * kept. The ones that drop off are soft-deleted, which takes them out of
 * the library without touching any application that was already sent
 * with them.
 *
 * A new file always gets a new name and a new row; nothing already in the
 * library is ever overwritten. If the row cannot be written, the file just
 * stored is removed again, so there is never a file without a document or
 * a document without its file.
 */
class StoreCandidateDocument
{
    public function __invoke(CandidateProfile $candidateProfile, DocumentType $type, UploadedFile $file): Document
    {
        return $this->add(
            $candidateProfile,
            $type,
            $file->store('documents', 'local'),
            $file->getClientOriginalName(),
        );
    }

    /**
     * A file the app made itself, such as a CV built from the profile.
     */
    public function generated(CandidateProfile $candidateProfile, DocumentType $type, string $contents, string $filename): Document
    {
        $path = 'documents/'.Str::random(40).'.'.strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        Storage::disk('local')->put($path, $contents);

        return $this->add($candidateProfile, $type, $path, $filename);
    }

    private function add(CandidateProfile $candidateProfile, DocumentType $type, string $path, string $filename): Document
    {
        try {
            return DB::transaction(function () use ($candidateProfile, $type, $path, $filename) {
                $document = $candidateProfile->documents()->create([
                    'document_type' => $type,
                    'file_path' => $path,
                    'original_filename' => $filename,
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
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
