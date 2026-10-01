<?php

namespace App\Models;

use App\Enums\ApplicationOutcomeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'application_id', 'changed_by_id',
    'from_stage', 'to_stage', 'from_outcome_status', 'to_outcome_status',
])]
class ApplicationEvent extends Model
{
    const UPDATED_AT = null;

    /**
     * Hire or rejection events that still stand: a decision followed by
     * an undo on the same application counts for nothing.
     */
    public function scopeStandingDecisions(Builder $query, ApplicationOutcomeStatus ...$outcomes): Builder
    {
        return $query
            ->whereIn('application_events.to_outcome_status', array_map(fn ($outcome) => $outcome->value, $outcomes))
            ->whereNotExists(fn ($undo) => $undo
                ->from('application_events as undo')
                ->whereColumn('undo.application_id', 'application_events.application_id')
                ->whereColumn('undo.id', '>', 'application_events.id')
                ->where('undo.to_outcome_status', ApplicationOutcomeStatus::Active->value));
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
