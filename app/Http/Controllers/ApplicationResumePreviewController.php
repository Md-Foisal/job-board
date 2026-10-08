<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

/**
 * The CV an application was sent with, drawn in the applicant page's CV
 * tab instead of downloaded -- the way Workable and Greenhouse show a
 * resume next to the profile. The same checks as the download beside it
 * (ApplicationResumeDownloadController): the application, not the file,
 * is what the company is entitled to.
 *
 * Only the kinds a browser draws by itself are served inline, from our
 * own list of types (DocumentPreviewController); anything else answers
 * 404 here and is downloaded instead.
 */
class ApplicationResumePreviewController extends Controller
{
    public function __invoke(Company $company, Application $application)
    {
        abort_unless($application->jobPosting->company_id === $company->id, 404);

        $this->authorize('downloadResume', $application);

        $document = $application->resumeDocument;

        abort_if($document === null, 404);
        abort_unless(DocumentPreviewController::canPreview($document), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->original_filename, [
            'Content-Type' => DocumentPreviewController::contentType($document),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
