<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

/**
 * The employer-side twin of DocumentDownloadController, and deliberately
 * not the same route: an employer is never granted access to a Document
 * directly, only to the one attached to an application their company
 * received. Gating on the application rather than on the file is what
 * keeps a CV from following its owner's id into someone else's hands.
 */
class ApplicationResumeDownloadController extends Controller
{
    public function __invoke(Company $company, Application $application)
    {
        // Same hand-written binding as the detail page: {application} has no
        // custom route key, so Laravel does not scope it to the company in
        // the URL, and somebody who works at two companies could otherwise
        // pull one company's CV through the other's address.
        abort_unless($application->jobPosting->company_id === $company->id, 404);

        $this->authorize('downloadResume', $application);

        $document = $application->resumeDocument;

        abort_if($document === null, 404);

        // A file erased with its owner's data leaves its row behind for the
        // application's record; it answers as gone rather than failing.
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
