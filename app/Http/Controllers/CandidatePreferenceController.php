<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Rules\CurrencyInUse;
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
            // A floor with no currency cannot be compared with any job.
            'desired_salary_currency' => ['required_with:desired_salary_min,desired_salary_max', 'nullable', 'string', new CurrencyInUse],
            'preferred_workplace_type' => ['nullable', Rule::enum(WorkplaceType::class)],
            'preferred_employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'available_from' => ['nullable', 'date'],
        ]);

        // Preference is a singleton per candidate profile, created here on
        // first save rather than provisioned eagerly at registration --
        // most candidates won't touch this page until they mean to.
        $request->user()->candidateProfile->preference()->updateOrCreate([], $validated);

        return back()->with('success', 'Preferences updated.');
    }
}
