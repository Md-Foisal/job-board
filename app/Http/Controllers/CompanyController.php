<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function create(): View
    {
        return view('companies.create');
    }

    public function store(StoreCompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $company = $createCompany($request->user(), $request->validated());

        return redirect()
            ->route('employer.dashboard', $company)
            ->with('success', __('Your company is set up.'));
    }

    public function show(Company $company): View
    {
        // The job cards on this page all belong to $company already, so
        // the relation is set manually rather than eager-loaded again --
        // one query instead of an extra one per card.
        $jobPostings = $company->jobPostings()
            ->active()
            ->latest('created_at')
            ->get()
            ->each(fn ($jobPosting) => $jobPosting->setRelation('company', $company));

        return view('companies.show', [
            'company' => $company,
            'jobPostings' => $jobPostings,
        ]);
    }
}
