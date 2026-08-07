<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

use App\Models\User;
use App\Models\JobListing;

#[Fillable(['user_id', 'name', 'type', 'logo', 'website', 'about'])]
class Organization extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobListings()
    {
        return $this->hasMany(JobListing::class);
    }
}
