<?php

namespace App\Services;

class ApplicationService
{
    public function alreadyApplied($request) {
      return $request->user()
        ->applications()
        ->where('job_listing_id', $request->job_listing_id)
        ->exists();
    }

    public function storeResume($request) {
      return $request->file('resume')->store('resumes', 'public');
    }
}