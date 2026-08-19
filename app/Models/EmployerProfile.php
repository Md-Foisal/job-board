<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\JobListing;

#[Fillable(['user_id', 'name', 'type', 'logo', 'cover_photo', 'website', 'about', 'founded_year', 'location'])]
class EmployerProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
        ];
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobListings()
    {
        return $this->hasMany(JobListing::class);
    }
}
