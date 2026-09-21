<?php

namespace App\Http\Controllers;

use App\Actions\ReplaceUploadedImage;
use App\Support\ImageUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $candidateProfile = $request->user()->candidateProfile;

        // The profile page bills itself as "what companies see when they
        // look you up", so it needs read-only summaries of every section a
        // real candidate profile has -- not just the identity card fields
        // that live directly on CandidateProfile. Education/Experience/
        // Documents/Skills each already have their own dedicated CRUD page
        // (claude/14 step 3b) and stay that way here; this view only reads
        // them, it never edits them.
        return view('candidate.profile.edit', [
            'user' => $request->user(),
            'candidateProfile' => $candidateProfile,
            'educationRecords' => $candidateProfile->educationRecords()->orderByDesc('start_date')->get(),
            'experienceRecords' => $candidateProfile->experienceRecords()->orderByDesc('start_date')->get(),
            'skills' => $candidateProfile->skills()->orderBy('name')->get(),
            'documents' => $candidateProfile->documents()->latest()->get(),
        ]);
    }

    public function update(Request $request, ReplaceUploadedImage $replaceImage): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['nullable', ...ImageUploads::rules(ImageUploads::PHOTO)],
            'cover_photo' => ['nullable', ...ImageUploads::rules(ImageUploads::COVER)],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
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
