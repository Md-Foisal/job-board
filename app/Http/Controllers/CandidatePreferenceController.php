<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CandidatePreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('candidate.preferences.edit', [
            'preference' => $request->user()->candidateProfile->preference,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'desired_salary_min' => ['nullable', 'integer', 'min:0'],
            'desired_salary_max' => ['nullable', 'integer', 'min:0', 'gte:desired_salary_min'],
            'desired_salary_currency' => ['nullable', 'string', 'size:3'],
            'preferred_workplace_type' => ['nullable', Rule::enum(WorkplaceType::class)],
            'preferred_employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'available_from' => ['nullable', 'date'],
        ]);

        // An unchecked checkbox is simply absent from the request body (the
        // browser never sends it), so it can't go through validate() as a
        // "boolean" rule the way the other fields do -- $request->boolean()
        // is the standard way to read it, defaulting to false when missing.
        $validated['is_actively_searching'] = $request->boolean('is_actively_searching');

        // Preference is a singleton per candidate profile, created here on
        // first save rather than provisioned eagerly at registration --
        // most candidates won't touch this page until they mean to.
        $request->user()->candidateProfile->preference()->updateOrCreate([], $validated);

        return back()->with('success', 'Preferences updated.');
    }
}
