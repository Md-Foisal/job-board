<?php

namespace App\Models;

use App\Builders\JobPostingQueryBuilder;
use App\Casts\SanitizedHtml;
use App\Enums\AccountStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\EmploymentType;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\SalaryPeriod;
use App\Enums\WorkplaceType;
use App\Models\Concerns\HiddenWhileReported;
use App\Models\Pivots\JobPostingSkillPivot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id', 'posted_by_id', 'title', 'slug', 'description',
    'employment_type', 'workplace_type', 'location_city', 'location_country',
    'min_experience_years', 'salary_min', 'salary_max', 'salary_currency',
    'salary_period', 'salary_negotiable', 'expires_at',
])]
class JobPosting extends Model
{
    use HasFactory, HiddenWhileReported;

    protected function casts(): array
    {
        return [
            'description' => SanitizedHtml::class,
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'min_experience_years' => 'integer',
            'salary_min_monthly' => 'integer',
            'salary_max_monthly' => 'integer',
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
     * Alerts that have already emailed this posting.
     */
    public function jobAlerts()
    {
        return $this->belongsToMany(JobAlert::class, 'job_alert_job_posting')->withTimestamps();
    }

    /**
     * Publicly visible: availability_status active, moderation approved,
     * not expired, not held back by open reports, and from a company the
     * public can see.
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('availability_status', AvailabilityStatus::Active)
            ->where('moderation_status', ModerationStatus::Approved)
            ->where('expires_at', '>', now())
            ->notHiddenByReports()
            ->fromActiveCompanies();
    }

    /**
     * Exclude job postings whose company is suspended or itself hidden
     * while reports about it are reviewed.
     */
    public function scopeFromActiveCompanies(Builder $query)
    {
        return $query->whereHas('company', function (Builder $q) {
            $q->where('account_status', AccountStatus::Active)->notHiddenByReports();
        });
    }

    /**
     * Multi-parameter filtering and sorting live in JobPostingQueryBuilder
     * (JobPosting::query()->skill($id)->salaryBetween($min, $max)...), so
     * they do not pile up here as scopes; single-purpose scopes stay on
     * the model.
     */
    public function newEloquentBuilder($query): JobPostingQueryBuilder
    {
        return new JobPostingQueryBuilder($query);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->availability_status === AvailabilityStatus::Active && ! $this->isExpired();
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
            && $this->company->isPubliclyVisible()
            && ! $this->isHiddenByReports();
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

    public function screeningQuestions()
    {
        return $this->hasMany(ScreeningQuestion::class)->orderBy('display_order');
    }

    public function jobViews()
    {
        return $this->hasMany(JobView::class);
    }

    /**
     * Moderation decisions taken against this record.
     */
    public function moderationEvents()
    {
        return $this->morphMany(ModerationEvent::class, 'subject');
    }

    /**
     * The most recent rejection, which carries the reason the employer
     * needs to fix. Filtered by action because dismissing later reports
     * also writes to this posting's trail, and that note is not the reason.
     */
    public function latestRejection()
    {
        return $this->morphOne(ModerationEvent::class, 'subject')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('action', ModerationAction::RejectJobPosting));
    }
}
