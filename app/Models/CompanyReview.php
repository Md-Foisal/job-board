<?php

namespace App\Models;

use App\Enums\JobAsDescribed;
use App\Enums\MembershipStatus;
use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id', 'application_id', 'candidate_profile_id',
    'overall_rating', 'communication_rating', 'job_as_described', 'title', 'body',
    'moderation_status', 'published_at',
    'response_body', 'response_status', 'responded_by_id', 'responded_at',
])]
class CompanyReview extends Model
{
    use HasFactory;

    /**
     * Mirrors the database default: a review is never public until staff
     * have read it.
     */
    protected $attributes = [
        'moderation_status' => ModerationStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'communication_rating' => 'integer',
            'job_as_described' => JobAsDescribed::class,
            'moderation_status' => ModerationStatus::class,
            'published_at' => 'datetime',
            'response_status' => ModerationStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * What the public sees: reviews staff have approved, in their current
     * wording. Reports never take one out of this; only a staff decision
     * does.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('company_reviews.moderation_status', ModerationStatus::Approved->value)
            ->whereNotNull('company_reviews.published_at');
    }

    /**
     * Published reviews the company has not answered, or whose answer
     * staff sent back: the ones still waiting on the company.
     */
    public function scopeAwaitingResponse(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('company_reviews.response_status')
            ->orWhere('company_reviews.response_status', ModerationStatus::Rejected->value));
    }

    public function hasPublishedResponse(): bool
    {
        return $this->response_status === ModerationStatus::Approved
            && $this->response_body !== null;
    }

    /**
     * Whether the company's answer was written before the review took its
     * current wording. The writer may rewrite a review after the company
     * answered; the answer stays, but readers are told what it answered.
     * published_at is when the current wording was approved, and an
     * answer can only be written to a published review, so an answer
     * older than that was written to earlier text.
     */
    public function responseAnswersEarlierVersion(): bool
    {
        return $this->responded_at !== null
            && $this->published_at !== null
            && $this->responded_at->lt($this->published_at);
    }

    /**
     * The reviews a staff member may see in the panel: not those about the
     * company they work for, and not their own. The panel shows the
     * application behind a review, and someone on the company's team can
     * look that application up from the other side -- together they name
     * the writer.
     */
    public function scopeModeratableBy(Builder $query, User $staff): Builder
    {
        return $query
            ->whereNotIn('company_reviews.company_id', Membership::query()
                ->where('user_id', $staff->id)
                ->where('status', MembershipStatus::Active->value)
                ->select('company_id'))
            ->when($staff->candidateProfile !== null, fn (Builder $query) => $query
                ->where('company_reviews.candidate_profile_id', '!=', $staff->candidateProfile->id));
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The application that entitles the writer to this review. It also
     * says who wrote it, which is why it is never shown outside the
     * staff panel.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }

    /**
     * Whether this user wrote the review.
     */
    public function isWrittenBy(User $user): bool
    {
        return $user->candidateProfile !== null
            && $this->candidate_profile_id === $user->candidateProfile->id;
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function moderationEvents()
    {
        return $this->morphMany(ModerationEvent::class, 'subject');
    }
}
