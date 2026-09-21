<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AlertFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Postings this alert has already emailed, so none is sent twice.
     */
    public function sentJobPostings()
    {
        return $this->belongsToMany(JobPosting::class, 'job_alert_job_posting')->withTimestamps();
    }

    /**
     * Active alerts whose turn has come, owned by someone who can still
     * receive mail from us. The margins absorb the few seconds a run takes,
     * so yesterday's 08:00:05 does not make today's 08:00:01 "too soon".
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('user', fn (Builder $user) => $user->where('account_status', AccountStatus::Active))
            ->where(fn (Builder $query) => $query
                ->whereNull('last_sent_at')
                ->orWhere(fn (Builder $query) => $query
                    ->where('frequency', AlertFrequency::Daily)
                    ->where('last_sent_at', '<=', now()->subHours(23)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('frequency', AlertFrequency::Weekly)
                    ->where('last_sent_at', '<=', now()->subDays(7)->addHour())));
    }
}
