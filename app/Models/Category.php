<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    use SoftDeletes;

    /**
     * The slug is chosen once, from the name, and kept through renames --
     * it can already be in a URL. Names that slug the same way (C and C#,
     * say) get a numbered suffix rather than a collision.
     */
    protected static function booted(): void
    {
        // A restored category is its own page again. Left in place, the
        // old merge link would send its address elsewhere the next time it
        // is removed, and a later merge into it could point it at itself.
        static::restoring(function (self $model) {
            $model->merged_into_id = null;
        });

        static::creating(function (self $model) {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug($model->name) ?: 'category';
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
}
