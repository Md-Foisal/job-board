<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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

    /**
     * The optional public face this user shows candidates when they post
     * jobs. Company-independent -- see RecruiterProfile.
     */
    public function recruiterProfile()
    {
        return $this->hasOne(RecruiterProfile::class);
    }

    /**
     * The companies this user currently works for. "Currently" is the
     * whole point: an ended membership leaves its row behind for
     * attribution but stops granting access, so anything that asks
     * "which companies are mine" has to filter on it.
     */
    public function activeCompanies()
    {
        return $this->belongsToMany(Company::class, 'memberships')
            ->wherePivot('status', MembershipStatus::Active)
            ->withPivot('role');
    }

    public function savedJobs()
    {
        return $this->belongsToMany(JobPosting::class, 'saved_jobs');
    }

    /**
     * Reports this user has filed against a job posting or company --
     * not reports made against the user (there is no such thing in the
     * current design; only Job/Company are reportable subjects).
     */
    public function reportsFiled()
    {
        return $this->hasMany(Report::class, 'reporter_id');
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
     * This user's role at the given company, or null if they do not
     * currently work there. Unlike canManage() this answers "what am I",
     * which is what ranking one person against another needs.
     */
    public function roleAt(Company $company): ?MembershipRole
    {
        return $this->memberships()
            ->where('company_id', $company->id)
            ->where('status', MembershipStatus::Active)
            ->first()?->role;
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

    /**
     * Unlike "candidate" and "employer", staff standing is stored rather
     * than derived: it is granted by the platform, not by anything the
     * user does. A null staff_role means no platform role at all.
     */
    public function isStaff(): bool
    {
        return $this->staff_role !== null;
    }

    /**
     * Gate for the Filament admin panel. Staff standing alone is not
     * enough: a suspended account loses panel access immediately,
     * without its staff_role having to be cleared as well.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff()
            && $this->account_status === AccountStatus::Active;
    }
}
