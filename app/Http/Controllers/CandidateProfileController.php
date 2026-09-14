<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('candidate.profile.edit', [
            'user' => $request->user(),
            'candidateProfile' => $request->user()->candidateProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
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
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = $request->file('avatar')->store('avatars', 'public');
            $user->save();
        }

        // Cover photo lives on CandidateProfile -- same idiom as
        // Company::cover_photo_path, so any future generic "replace one
        // image" helper can serve both.
        if ($request->hasFile('cover_photo')) {
            if ($candidateProfile->cover_photo_path) {
                Storage::disk('public')->delete($candidateProfile->cover_photo_path);
            }

            $validated['cover_photo_path'] = $request->file('cover_photo')->store('candidate-covers', 'public');
        }

        unset($validated['avatar'], $validated['cover_photo']);

        $candidateProfile->update($validated);

        return back()->with('success', 'Profile updated.');
    }
}
