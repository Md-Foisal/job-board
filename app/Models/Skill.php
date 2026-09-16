<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    use SoftDeletes;

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class);
    }

    public function candidateProfiles()
    {
        return $this->belongsToMany(CandidateProfile::class);
    }

    /**
     * Partial, case-insensitive name match -- same idiom as
     * JobPostingQueryBuilder::keyword(), used for the skill-selection
     * page's live-as-you-type autocomplete (claude/14 route 17).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', "%{$term}%");
    }
}
