<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'application_id', 'changed_by_id',
    'from_stage', 'to_stage', 'from_outcome_status', 'to_outcome_status',
])]
class ApplicationEvent extends Model
{
    const UPDATED_AT = null;

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
