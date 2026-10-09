<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['candidate_profile_id', 'name', 'issuer', 'issued_on', 'expires_on', 'credential_id', 'credential_url'])]
class Certification extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * The most recently issued first.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('issued_on')->orderByDesc('id');
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * Past its expiry date: still listed, since the candidate held it,
     * but said so rather than passed off as current.
     */
    public function hasExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->endOfDay()->isPast();
    }
}
