<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\AccountStatus;
use App\Enums\DomainCheck;
use App\Enums\IdentityType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\ModerationAction;
use App\Models\Concerns\HiddenWhileReported;
use App\Support\EmailDomain;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug', 'identity_type', 'description', 'website_url', 'logo_path', 'cover_photo_path', 'size', 'industry'])]
class Company extends Model
{
    use HasFactory, HiddenWhileReported;

    /**
     * Mirrors the database defaults so a freshly created record already
     * knows them. Without this the column is simply absent until the row
     * is read back, and every check against it quietly sees null --
     * a gap preventAccessingMissingAttributes does not close, because it
     * deliberately stays silent on recently created models.
     */
    protected $attributes = [
        'account_status' => AccountStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'description' => SanitizedHtml::class,
            'identity_type' => IdentityType::class,
            'account_status' => AccountStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Whether a visitor may see this company's profile: not banned, and
     * not held back while reports about it are reviewed.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->account_status === AccountStatus::Active
            && ! $this->isHiddenByReports();
    }

    /**
     * Reports filed against this company. Job postings carry the same
     * relation -- those are the two things a user can report.
     */
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Moderation decisions taken against this record.
     */
    public function moderationEvents()
    {
        return $this->morphMany(ModerationEvent::class, 'subject');
    }

    /**
     * How many of a company's postings staff must approve by hand before
     * its new ones go live without waiting.
     */
    public const TRUSTED_AFTER_APPROVALS = 3;

    /**
     * Whether this company's postings skip the moderation queue.
     *
     * A new employer's postings are always read by a person first; after
     * a few have been approved and none rejected, the rest go straight
     * through. One queue reviewer cannot read a thousand postings, and
     * scam posters rarely build a clean record first.
     *
     * The record is read from the moderation trail rather than from the
     * postings' current state, so it cannot be laundered: a rejected
     * posting that is later fixed and approved still counts as a
     * rejection, and editing an approved posting does not erase the fact
     * that it was approved. A banned company is never trusted.
     */
    public function isTrustedPoster(): bool
    {
        if ($this->account_status !== AccountStatus::Active) {
            return false;
        }

        $record = $this->postingModerationRecord();

        return $record['rejected'] === 0
            && $record['approved'] >= self::TRUSTED_AFTER_APPROVALS;
    }

    /**
     * How many of this company's postings staff have approved, and how
     * many they have rejected, over its whole history. The trust tier
     * above is decided from it, and reviewers are shown it, so both are
     * reading the same numbers.
     *
     * @return array{approved: int, rejected: int}
     */
    public function postingModerationRecord(): array
    {
        $decisions = ModerationEvent::query()
            ->where('subject_type', (new JobPosting)->getMorphClass())
            ->whereIn('subject_id', $this->jobPostings()->select('id'))
            ->whereIn('action', [ModerationAction::ApproveJobPosting, ModerationAction::RejectJobPosting])
            ->get(['subject_id', 'action']);

        return [
            'approved' => $decisions->where('action', ModerationAction::ApproveJobPosting)->unique('subject_id')->count(),
            'rejected' => $decisions->where('action', ModerationAction::RejectJobPosting)->unique('subject_id')->count(),
        ];
    }

    /**
     * The cheapest real check a reviewer has (the one LinkedIn relies
     * on): does anyone who runs this company have an email address on the
     * company's own website domain?
     *
     * Only owners and managers count -- an ordinary member could be anyone
     * the company let in. A match on any one of them is enough, since a
     * founder signing up from a personal address and a manager with a
     * work address is the ordinary case.
     */
    public function domainCheck(): DomainCheck
    {
        $host = EmailDomain::host($this->website_url);

        if ($host === null) {
            return DomainCheck::NoWebsite;
        }

        $domains = $this->managerEmailDomains();

        if ($domains->contains(fn (string $domain) => EmailDomain::belongsTo($domain, $host))) {
            return DomainCheck::Match;
        }

        return $domains->every(fn (string $domain) => EmailDomain::isPersonal($domain))
            ? DomainCheck::PersonalEmail
            : DomainCheck::Mismatch;
    }

    /**
     * The active owners and managers, the people whose work addresses the
     * verification check compares with the website. A relation rather than
     * a query so a list of companies can load them all in one go.
     */
    public function managingMemberships()
    {
        return $this->hasMany(Membership::class)
            ->where('status', MembershipStatus::Active)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Manager])
            ->with('user:id,email');
    }

    /**
     * @return Collection<int, string>
     */
    public function managerEmailDomains()
    {
        return $this->managingMemberships
            ->map(fn ($membership) => EmailDomain::of($membership->user->email))
            ->unique()
            ->values();
    }

    /**
     * The people who can act for this company right now -- active owners
     * and managers. Everything the platform tells a company goes to them:
     * a plain member cannot post or decide, so mail to them is noise they
     * learn to ignore, and an ended membership hears nothing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function decisionMakers()
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('company_id', $this->id)
                ->where('status', MembershipStatus::Active)
                ->whereIn('role', [MembershipRole::Owner, MembershipRole::Manager]))
            ->get();
    }

    /**
     * The latest step in the verification conversation: verified, revoked,
     * or asked for documents. Bans and report dismissals share the trail
     * but say nothing about verification, so they are left out.
     */
    public function latestVerificationDecision()
    {
        return $this->morphOne(ModerationEvent::class, 'subject')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('action', [
                ModerationAction::VerifyCompany,
                ModerationAction::RevokeCompanyVerification,
                ModerationAction::RequestCompanyDocuments,
            ]));
    }

    /**
     * What staff asked this company to provide, while the request is
     * still the open step. Null once the company is verified or the
     * request has been superseded.
     */
    public function outstandingDocumentsRequest(): ?string
    {
        $decision = $this->latestVerificationDecision;

        return ! $this->verified_at && $decision?->action === ModerationAction::RequestCompanyDocuments
            ? $decision->reason
            : null;
    }
}
