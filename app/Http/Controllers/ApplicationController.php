<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ApplicationRequest;
use App\Services\ApplicationService;
use App\Events\JobApplicationSubmitted;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applicationService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $applications = auth()
        ->user()
        ->applications()
        ->with([
            'jobListing:id,title,location,employment_type,workplace_type,salary_min,salary_max,salary_currency,salary_period,status,created_at,user_id,employer_profile_id',
            'jobListing.user:id,name',
            'jobListing.employerProfile:id,name,verified',
        ])
        ->latest()
        ->paginate(10);

        return view('applications.index', compact('applications'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        
        // Phase 4: don't let a candidate start an application against a job
        // listing whose owning employer account has since been soft-deleted.
        $jobListing = JobListing::fromActiveUsers()
            ->with(['user:id,name', 'employerProfile:id,name,verified'])
            ->findOrFail($request->query('job_listing_id'));
        return view('applications.create', compact('jobListing'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ApplicationRequest $request)
    {
        $alreadyApplied = $this->applicationService->alreadyApplied($request);
        
        if ($alreadyApplied) {
            return redirect()->back()->withErrors(['You have already applied for this job listing.']);
            }
            
            $validatedData = $request->validated();
            $validatedData['resume'] = $this->applicationService->storeResume($request);
            
            $application = $request->user()->applications()->create($validatedData);
        
        JobApplicationSubmitted::dispatch($application);

        return redirect()->route('applications.index')->with('success', 'Application submitted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Application $application)
    {
        $this->authorize('view', $application);

        $application->load([
            'jobListing:id,title,location,employment_type,workplace_type,salary_min,salary_max,salary_currency,salary_period,status,created_at,user_id,employer_profile_id',
            'jobListing.user:id,name',
            'jobListing.employerProfile:id,name,verified',
        ]);
        return view('applications.show', compact('application'));
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Application $application)
    {
        $this->authorize('delete', $application);

        Storage::disk('public')->delete($application->resume);
        $application->delete();

        return redirect()->route('applications.index')->with('success', 'Application deleted successfully.');
    }
}
