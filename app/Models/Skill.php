<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\JobListing;
use App\Models\CandidateProfile;

#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    public function jobListings()
    {
        return $this->belongsToMany(JobListing::class);
    }

    public function candidateProfiles()
    {
        return $this->belongsToMany(CandidateProfile::class);
    }
}
