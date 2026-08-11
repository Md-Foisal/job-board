<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Application;
use App\Models\EmployerProfile;
use App\Models\Category;
use App\Models\Skill;


#[Fillable(['title', 'company', 'description', 'location', 'salary_min', 'salary_max', 'salary_currency', 'salary_period', 'type'])]
class JobListing extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function employerProfile()
    {
        return $this->belongsTo(EmployerProfile::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class)->withPivot('importance');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
