<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['candidate_profile_id', 'role', 'organization', 'start_date', 'end_date', 'description'])]
class VolunteerExperience extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Ongoing ones first, then the most recently finished, as roles are
     * listed.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('end_date is not null')
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
