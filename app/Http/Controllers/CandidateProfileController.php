<?php

namespace App\Http\Controllers;

use App\Actions\ReplaceUploadedImage;
use App\Support\ContactDetails;
use App\Support\ImageUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $candidateProfile = $request->user()->candidateProfile;

        return view('candidate.profile.edit', [
            'candidateProfile' => $candidateProfile,
            'documentCount' => $candidateProfile->documents()->count(),
        ]);
    }

    /**
     * The candidate's own profile drawn the way a company reads it, so
     * "what the company sees" is something they can look at rather than
     * take on trust.
     */
    public function preview(Request $request): View
    {
        return view('candidate.profile.preview', [
            'candidateProfile' => $request->user()->candidateProfile,
        ]);
    }

    public function update(Request $request, ReplaceUploadedImage $replaceImage): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['nullable', ...ImageUploads::rules(ImageUploads::PHOTO)],
            'cover_photo' => ['nullable', ...ImageUploads::rules(ImageUploads::COVER)],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'portfolio_url' => ['nullable', 'url:http,https', 'max:255'],
            'github_url' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'phone' => ContactDetails::phoneRules(),
            'location' => ContactDetails::locationRules(),
        ], [
            'phone.regex' => __('Enter a phone number using digits, with an optional + and country code.'),
        ]);

        $user = $request->user();
        $candidateProfile = $user->candidateProfile;

        // Avatar lives on the User model (shared by every role), not on
        // CandidateProfile -- the starter kit already has this column,
        // it just had no upload UI anywhere yet.
        $avatarPath = $replaceImage($request->file('avatar'), $user->avatar, 'avatars', longestSide: ImageUploads::storedLongestSide(ImageUploads::PHOTO));

        if ($avatarPath !== $user->avatar) {
            $user->avatar = $avatarPath;
            $user->save();
        }

        $validated['cover_photo_path'] = $replaceImage(
            $request->file('cover_photo'),
            $candidateProfile->cover_photo_path,
            'candidate-covers',
            longestSide: ImageUploads::storedLongestSide(ImageUploads::COVER),
        );

        unset($validated['avatar'], $validated['cover_photo']);

        $candidateProfile->update($validated);

        return back()->with('success', 'Profile updated.');
    }
}
