<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use \Illuminate\Database\Eloquent\Builder;

use App\Models\User;
use App\Models\Company;
use App\Models\Application;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Pivots\JobPostingSkillPivot;
use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Enums\SalaryPeriod;
use App\Enums\AvailabilityStatus;
use App\Enums\ModerationStatus;
use App\Enums\AccountStatus;


#[Fillable([
    'company_id', 'posted_by_id', 'title', 'slug', 'description',
    'employment_type', 'workplace_type', 'location_city', 'location_country',
    'min_experience_years', 'salary_min', 'salary_max', 'salary_currency',
    'salary_period', 'salary_negotiable', 'expires_at',
])]
class JobPosting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'min_experience_years' => 'integer',
            'salary_negotiable' => 'boolean',
            'employment_type' => EmploymentType::class,
            'workplace_type' => WorkplaceType::class,
            'salary_period' => SalaryPeriod::class,
            'availability_status' => AvailabilityStatus::class,
            'moderation_status' => ModerationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JobPosting $job) {
            // 40 hours/week * 4.33 weeks/month ~ 173; 52 weeks/year / 12 months
            $monthlyFactors = [
                'hourly' => 173,
                'weekly' => 4.33,
                'monthly' => 1,
                'yearly' => 1 / 12,
            ];

            $period = $job->salary_period instanceof SalaryPeriod
                ? $job->salary_period->value
                : $job->salary_period;

            $factor = $monthlyFactors[$period] ?? null;

            $job->salary_min_monthly = ($job->salary_min !== null && $factor !== null)
                ? round($job->salary_min * $factor)
                : null;

            $job->salary_max_monthly = ($job->salary_max !== null && $factor !== null)
                ? round($job->salary_max * $factor)
                : null;
        });
    }

    /**
     * Publicly visible: availability_status active, moderation approved,
     * not expired, and the owning company's own account not suspended.
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('availability_status', AvailabilityStatus::Active)
            ->where('moderation_status', ModerationStatus::Approved)
            ->where('expires_at', '>', now())
            ->fromActiveCompanies();
    }

    /**
     * Exclude job postings whose owning company has been suspended.
     */
    public function scopeFromActiveCompanies(Builder $query)
    {
        return $query->whereHas('company', function (Builder $q) {
            $q->where('account_status', AccountStatus::Active);
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->availability_status === AvailabilityStatus::Active && !$this->isExpired();
    }

    /**
     * Whether this posting should be visible on the public, unauthenticated
     * listing/detail pages -- the single-record equivalent of scopeActive(),
     * used by JobPostingPolicy::view() to decide guest/candidate access.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->moderation_status === ModerationStatus::Approved
            && $this->isOpen()
            && $this->company->account_status === AccountStatus::Active;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class)->withPivot('importance')->using(JobPostingSkillPivot::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
