<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\JobListing;

#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    public function jobListings()
    {
        return $this->belongsToMany(JobListing::class);
    }
}
