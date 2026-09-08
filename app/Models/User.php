<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Company;
use App\Models\Membership;
use App\Models\Invitation;
use App\Models\JobPosting;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\AccountStatus;
use App\Enums\StaffRole;

#[Fillable(['name', 'email', 'password', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_status' => AccountStatus::class,
            'staff_role' => StaffRole::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function invitationsSent()
    {
        return $this->hasMany(Invitation::class, 'invited_by_id');
    }

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class, 'posted_by_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function candidateProfile()
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function savedJobs()
    {
        return $this->belongsToMany(JobPosting::class, 'saved_jobs');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * "Candidate" is not a stored role — the existence of a
     * CandidateProfile row is what makes this true.
     */
    public function isCandidate(): bool
    {
        return $this->candidateProfile()->exists();
    }

    /**
     * Whether this user is employer-side at all — has at least one
     * active membership, regardless of which company. Unlike worksAt(),
     * this doesn't ask about a specific company; useful in contexts
     * (e.g. middleware) where no company is in scope yet.
     */
    public function isEmployer(): bool
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /**
     * Whether this user is actively working at the given company
     * (in any role).
     */
    public function worksAt(Company $company): bool
    {
        return $this->memberships()
            ->where('company_id', $company->id)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /**
     * Whether this user can make ownership-level decisions for the
     * given company (owner/manager) — a plain member cannot.
     */
    public function canManage(Company $company): bool
    {
        return $this->memberships()
            ->where('company_id', $company->id)
            ->where('status', MembershipStatus::Active)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Manager])
            ->exists();
    }
}
