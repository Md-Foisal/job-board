<?php

namespace App\Http\Controllers;

use App\Actions\ReplaceUploadedImage;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Support\ImageUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployerCompanyController extends Controller
{
    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('employer.company.edit', [
            'company' => $company,
        ]);
    }

    public function update(
        UpdateCompanyRequest $request,
        Company $company,
        ReplaceUploadedImage $replaceImage,
    ): RedirectResponse {
        $validated = $request->validated();

        $validated['logo_path'] = $replaceImage(
            $request->file('logo'),
            $company->logo_path,
            'company-logos',
            longestSide: ImageUploads::storedLongestSide(ImageUploads::LOGO),
        );

        $validated['cover_photo_path'] = $replaceImage(
            $request->file('cover_photo'),
            $company->cover_photo_path,
            'company-covers',
            longestSide: ImageUploads::storedLongestSide(ImageUploads::COVER),
        );

        // The form's file inputs are named for what they are to the person
        // filling them in; the columns are named for what they store.
        unset($validated['logo'], $validated['cover_photo']);

        $company->update($validated);

        return back()->with('success', __('Company profile updated.'));
    }
}
