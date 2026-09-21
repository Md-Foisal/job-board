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
use Illuminate\Database\Eloquent\Builder;
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
     * How long a deleted account can still be restored before its
     * personal data is erased for good. 30 days is what Facebook settled on.
     */
    public const DELETION_GRACE_DAYS = 30;

    /**
     * Mirrors the database defaults so a freshly created record already
     * knows them. Without this the column is simply absent until the row
     * is read back, and every check against it quietly sees null --
     * a gap preventAccessingMissingAttributes does not close, because it
     * deliberately stays silent on recently created models.
     */
    protected $attributes = [
        'account_status' => AccountStatus::Active->value,
        'staff_role' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'anonymized_at' => 'datetime',
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
     * Only the memberships that currently grant access. Loaded whole and
     * unfiltered by LoadStaffMemberships so worksAt() can answer from
     * memory -- never eager-load it with extra conditions.
     */
    public function activeMemberships()
    {
        return $this->hasMany(Membership::class)->where('status', MembershipStatus::Active);
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

    public function jobAlerts()
    {
        return $this->hasMany(JobAlert::class);
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
     * Deleted by its owner, not yet erased, and still inside the grace
     * period -- the only state a self-service restore can undo.
     */
    public function isRestorable(): bool
    {
        return $this->trashed()
            && $this->anonymized_at === null
            && $this->deleted_at->greaterThan(now()->subDays(self::DELETION_GRACE_DAYS));
    }

    /**
     * When a deleted account's data will be erased, if nobody restores it.
     */
    public function erasesAt(): ?\Carbon\CarbonInterface
    {
        return $this->trashed() && $this->anonymized_at === null
            ? $this->deleted_at->copy()->addDays(self::DELETION_GRACE_DAYS)
            : null;
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
     *
     * Answered from memory when activeMemberships has been loaded. A
     * moderation table asks this two or three times for every row -- once
     * per action it decides whether to show -- so the admin panel loads the
     * viewer's active memberships once per request instead of sending a
     * query per question. It deliberately does not trust a loaded
     * `memberships` relation: that one gets eager-loaded with filters
     * elsewhere, and a filtered list would make this answer "no" wrongly.
     */
    public function worksAt(Company $company): bool
    {
        if ($this->relationLoaded('activeMemberships')) {
            return $this->activeMemberships->contains('company_id', $company->id);
        }

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

    public function isSuperAdmin(): bool
    {
        return $this->staff_role === StaffRole::SuperAdmin;
    }

    /**
     * Staff standing alone is not enough to act: a suspended account
     * loses its platform powers immediately, without its staff_role
     * having to be cleared as well. Every moderation policy asks this
     * question, so it is defined once, here.
     */
    public function isActiveStaff(): bool
    {
        return $this->isStaff()
            && $this->account_status === AccountStatus::Active;
    }

    /**
     * Staff who can act on a queue right now: not suspended, and with the
     * two-factor setup the panel demands, so nobody is told about work they
     * cannot get to.
     */
    public function scopeActiveStaff(Builder $query): Builder
    {
        return $query->whereNotNull('staff_role')
            ->where('account_status', AccountStatus::Active)
            ->whereNotNull('two_factor_confirmed_at');
    }

    /**
     * Gate for the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActiveStaff();
    }

    /**
     * Moderation decisions taken against this user (suspension and the
     * like) -- not the ones they took as staff, which are the events
     * whose admin_id is theirs.
     */
    public function moderationEvents()
    {
        return $this->morphMany(ModerationEvent::class, 'subject');
    }
}
