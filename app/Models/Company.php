<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\AccountStatus;
use App\Enums\IdentityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'identity_type', 'description', 'website_url', 'logo_path', 'cover_photo_path', 'size', 'industry'])]
class Company extends Model
{
    use HasFactory;

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
}
