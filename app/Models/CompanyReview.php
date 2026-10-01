<?php

namespace App\Models;

use App\Enums\JobAsDescribed;
use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
