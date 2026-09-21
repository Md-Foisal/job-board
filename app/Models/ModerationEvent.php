<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['admin_id', 'subject_type', 'subject_id', 'action', 'reason'])]
class ModerationEvent extends Model
{
    const UPDATED_AT = null;

    /**
     * Append-only. Hiding the edit and delete buttons in the panel is not
     * enough on its own: no code path may rewrite or remove the trail.
     * (A staff account being hard-deleted still clears admin_id, which the
     * database does, not the model.)
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Moderation events are a permanent record and cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Moderation events are a permanent record and cannot be deleted.'));
    }

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
