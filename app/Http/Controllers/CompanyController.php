<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, Company $company): View
    {
        // A banned or reported-and-hidden company is not there for the
        // public, and a 404 says so without saying why. Its own people
        // still see the page, so they can check what candidates will see
        // once it is back.
        abort_unless(
            $company->isPubliclyVisible() || $request->user()?->worksAt($company),
            404,
        );

        // The job cards on this page all belong to $company already, so
        // the relation is set manually rather than eager-loaded again --
        // one query instead of an extra one per card.
        $jobPostings = $company->jobPostings()
            ->active()
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->each(fn ($jobPosting) => $jobPosting->setRelation('company', $company));

        return view('companies.show', [
            'company' => $company,
            'jobPostings' => $jobPostings,
        ]);
    }
}
