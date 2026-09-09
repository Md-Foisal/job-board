<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'candidate_profile_id',
    'desired_salary_min',
    'desired_salary_max',
    'desired_salary_currency',
    'preferred_workplace_type',
    'preferred_employment_type',
    'is_actively_searching',
    'available_from',
])]
class CandidatePreference extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'desired_salary_min' => 'integer',
            'desired_salary_max' => 'integer',
            'preferred_workplace_type' => WorkplaceType::class,
            'preferred_employment_type' => EmploymentType::class,
            'is_actively_searching' => 'boolean',
            'available_from' => 'date',
        ];
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
