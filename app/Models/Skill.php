<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\JobPosting;
use App\Models\CandidateProfile;

#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class);
    }

    public function candidateProfiles()
    {
        return $this->belongsToMany(CandidateProfile::class);
    }
}
