<?php

namespace App\Models\Concerns;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * Something the public can report is taken out of public view once enough
 * different people have reported it, and comes back by itself when staff
 * close those reports. Nothing is stored: the open reports are the state,
 * so dismissing, approving or rejecting cannot leave it stuck hidden.
 */
trait HiddenWhileReported
{
    public function scopeNotHiddenByReports(Builder $query): Builder
    {
        return $query->whereNotIn(
            $this->qualifyColumn($this->getKeyName()),
            Report::subjectsHiddenPendingReview($this->getMorphClass()),
        );
    }

    public function isHiddenByReports(): bool
    {
        return Report::subjectsHiddenPendingReview($this->getMorphClass())
            ->where('reportable_id', $this->getKey())
            ->exists();
    }
}
