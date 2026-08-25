<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use \Illuminate\Database\Eloquent\Builder;

use App\Models\User;
use App\Models\Application;
use App\Models\EmployerProfile;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Pivots\JobListingSkillPivot;
use App\Enums\JobListingStatus;
use App\Enums\ModerationStatus;


#[Fillable(['title', 'description', 'location', 'salary_min', 'salary_max', 'salary_currency', 'salary_period', 'employment_type', 'work_location', 'employer_profile_id', 'expires_at'])]
class JobListing extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'status' => JobListingStatus::class,
            'moderation_status' => ModerationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JobListing $job) {
            // 40 hours/week * 4.33 weeks/month ~ 173; 52 weeks/year / 12 months
            $monthlyFactors = [
                'hourly' => 173,
                'weekly' => 4.33,
                'monthly' => 1,
                'yearly' => 1 / 12,
            ];

            $factor = $monthlyFactors[$job->salary_period] ?? null;

            $job->salary_min_monthly = ($job->salary_min !== null && $factor !== null)
                ? round($job->salary_min * $factor)
                : null;

            $job->salary_max_monthly = ($job->salary_max !== null && $factor !== null)
                ? round($job->salary_max * $factor)
                : null;
        });
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', JobListingStatus::Open)
        ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->status === JobListingStatus::Open && !$this->isExpired();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function employerProfile()
    {
        return $this->belongsTo(EmployerProfile::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class)->withPivot('importance')->using(JobListingSkillPivot::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
