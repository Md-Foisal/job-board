<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use Illuminate\Http\Request;
use App\Http\Requests\JobListingRequest;
use App\Models\Category;
use App\Models\Skill;

class JobListingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('job-listings.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->employerProfile) {
            return redirect()->route('employer.profile')->with('error', 'You need to create an employer profile before posting a job listing.');
        }
        $categories = Category::all();
        $skills = Skill::all();
        return view('job-listings.create', compact('categories', 'skills'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobListingRequest $request)
    {
        if (!auth()->user()->employerProfile) {
            return redirect()->route('employer.profile')->with('error', 'You need to create an employer profile before posting a job listing.');
        }

        $validatedData = $request->validated();
        $validatedData['employer_profile_id'] = auth()->user()->employerProfile->id;

        
        $categoryIds = $validatedData['categories'];
        $skillsToSync = [];
        foreach ($validatedData['skills'] as $skillId => $skill) {
            if ($skill['selected'] ?? false) {
                $skillsToSync[$skillId] = ['importance' => $skill['importance']];
            }
        }
        unset($validatedData['categories'], $validatedData['skills']);

        $jobListing = $request->user()->jobListings()->create($validatedData);
        $jobListing->categories()->sync($categoryIds);
        $jobListing->skills()->sync($skillsToSync);

        return redirect()->route('job-listings.index')->with('success', 'Job listing created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JobListing $jobListing)
    {
        $jobListing->load(['user:id,name,email', 'employerProfile:id,name,verified']);

        // The owning user's own SoftDeletes global scope means a deleted
        // account's user simply resolves to null here (Phase 4: soft-delete
        // query safety) -- treat that the same as the listing not existing,
        // instead of rendering a page with a null employer.
        abort_unless($jobListing->user, 404);

        return view('job-listings.show', compact('jobListing'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobListing $jobListing)
    {
        $this->authorize('update', $jobListing);
        $categories = Category::all();
        $skills = Skill::all();

        return view('job-listings.edit', compact('jobListing', 'categories', 'skills'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobListingRequest $request, JobListing $jobListing)
    {
        $this->authorize('update', $jobListing);

        $validatedData = $request->validated();
        $categoryIds = $validatedData['categories'];
        $skillsToSync = []; 
        foreach ($validatedData['skills'] as $skillId => $skill) {
            if ($skill['selected'] ?? false) {
                $skillsToSync[$skillId] = ['importance' => $skill['importance']];
            }
        }
        unset($validatedData['categories'], $validatedData['skills']);

        $jobListing->update($validatedData);
        $jobListing->categories()->sync($categoryIds);
        $jobListing->skills()->sync($skillsToSync);

        return redirect()->route('job-listings.show', $jobListing)->with('success', 'Job listing updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobListing $jobListing)
    {
        $this->authorize('delete', $jobListing);

        // Phase 4 (deletion guard): once a candidate has applied, the
        // listing becomes part of their application history -- hard
        // deleting it would erase that record. Only an application-free
        // listing can be removed outright; otherwise the employer is told
        // no (a proper "close listing" action is separate, unbuilt
        // Phase B*UI work).
        if ($jobListing->applications()->exists()) {
            return redirect()->route('job-listings.show', $jobListing)
                ->with('error', 'This job listing has applications and cannot be deleted, to preserve applicant history.');
        }

        $jobListing->delete();

        return redirect()->route('job-listings.index')->with('success', 'Job listing deleted successfully.');
    }
}
