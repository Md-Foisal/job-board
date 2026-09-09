<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\JobPosting;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    use SoftDeletes;

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class);
    }
}
