<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['title', 'company', 'description', 'location', 'salary', 'type'])]
class JobListing extends Model
{
    use HasFactory;
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
