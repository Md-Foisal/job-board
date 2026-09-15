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
        $this->authorize('downloadResume', $application);

        $document = $application->resumeDocument;

        abort_if($document === null, 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }
}
