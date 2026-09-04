<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Models\User;
use App\Enums\ReportStatus;

#[Fillable(['user_id', 'reason', 'details', 'moderation_status'])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'moderation_status' => ReportStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reportable()
    {
        return $this->morphTo();
    }
}
