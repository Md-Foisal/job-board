<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\AccountStatus;
use App\Enums\IdentityType;
use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'identity_type', 'description', 'website_url', 'logo_path', 'cover_photo_path', 'size', 'industry'])]
class Company extends Model
{
    use HasFactory;

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
}
