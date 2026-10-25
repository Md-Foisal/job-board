<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['candidate_profile_id', 'title', 'publisher', 'published_on', 'url', 'description'])]
class Publication extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
        ];
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_on')->orderByDesc('id');
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
