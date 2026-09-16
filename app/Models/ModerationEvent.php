<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['admin_id', 'subject_type', 'subject_id', 'action', 'reason'])]
class ModerationEvent extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
        ];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
