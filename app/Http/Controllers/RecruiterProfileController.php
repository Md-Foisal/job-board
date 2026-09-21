<?php

namespace App\Http\Controllers;

use App\Actions\ReplaceUploadedImage;
use App\Http\Requests\UpdateRecruiterProfileRequest;
use App\Support\ImageUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecruiterProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('employer.recruiter-profile.edit', [
            'recruiterProfile' => $request->user()->recruiterProfile,
            // The profile is the same whichever company you post for, so the
            // company only decides which workspace frames the page: the one
            // it was opened from (?company=), if it is really one of theirs,
            // so someone in two companies is not dropped into the other one.
            'company' => $this->frame($request),
        ]);
    }

    public function update(
        UpdateRecruiterProfileRequest $request,
        ReplaceUploadedImage $replaceImage,
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->validated();

        $validated['avatar_path'] = $replaceImage(
            $request->file('avatar'),
            $user->recruiterProfile?->avatar_path,
            'recruiter-avatars',
            longestSide: ImageUploads::storedLongestSide(ImageUploads::PHOTO),
        );

        unset($validated['avatar']);

        // Created on first save rather than at registration: it is
        // optional, and a row full of nulls would claim a face that was
        // never chosen.
        $user->recruiterProfile()->updateOrCreate([], $validated);

        return back()->with('success', __('Your recruiter profile is updated.'));
    }

    private function frame(Request $request)
    {
        $companies = $request->user()->activeCompanies();

        return $request->filled('company')
            ? ((clone $companies)->where('companies.slug', $request->query('company'))->first() ?? $companies->first())
            : $companies->first();
    }
}
