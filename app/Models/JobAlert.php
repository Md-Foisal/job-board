<?php

namespace App\Models;

use App\Enums\AlertFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A job search a candidate has asked to be emailed about. The criteria
 * are the same filters the search page uses (see JobSearchCriteria), so
 * an alert matches exactly what the search showed when it was saved.
 */
#[Fillable(['name', 'criteria', 'frequency', 'is_active'])]
class JobAlert extends Model
{
    use HasFactory;

    /**
     * Enough for every search anyone keeps an eye on; more than this is a
     * mailbox being filled, not a job being looked for.
     */
    public const MAX_PER_CANDIDATE = 20;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'frequency' => AlertFrequency::class,
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
