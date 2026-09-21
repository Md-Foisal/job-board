<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Skill extends Model
{
    use SoftDeletes;

    /**
     * The slug is chosen once, from the name, and kept through renames --
     * it can already be in a URL. Names that slug the same way (C and C#,
     * say) get a numbered suffix rather than a collision.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug($model->name) ?: 'skill';
            $slug = $base;
            $suffix = 2;

            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            $model->slug = $slug;
        });
    }

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
