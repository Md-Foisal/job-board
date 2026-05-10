<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use Illuminate\Http\Request;
use App\Http\Requests\JobListingRequest;

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
        return view('job-listings.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobListingRequest $request)
    {

        $request->user()->jobListings()->create($request->validated());

        return redirect()->route('job-listings.index')->with('success', 'Job listing created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JobListing $jobListing)
    {
        $jobListing->load('user:id,name,email');
        return view('job-listings.show', compact('jobListing'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobListing $jobListing)
    {
        $this->authorize('update', $jobListing);

        return view('job-listings.edit', compact('jobListing'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobListingRequest $request, JobListing $jobListing)
    {
        $this->authorize('update', $jobListing);

        $jobListing->update($request->validated());

        return redirect()->route('job-listings.show', $jobListing)->with('success', 'Job listing updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobListing $jobListing)
    {
        $this->authorize('delete', $jobListing);

        $jobListing->delete();

        return redirect()->route('job-listings.index')->with('success', 'Job listing deleted successfully.');
    }
}
