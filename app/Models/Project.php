<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['candidate_profile_id', 'name', 'description', 'url', 'source_url', 'start_date', 'end_date'])]
class Project extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'description' => SanitizedHtml::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Ongoing ones first, then the most recently finished, then the most
     * recently started -- the order a CV lists work in.
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
