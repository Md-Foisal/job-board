<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Company;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;

#[Fillable(['user_id', 'company_id', 'role', 'job_title', 'status'])]
class Membership extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Whether removing or demoting this membership would leave its
     * company with nobody in charge. Ownership is transferred by
     * promoting someone else first, never by vacating the seat -- the
     * same continuity rule Slack enforces by refusing to demote a
     * primary owner, and that GitHub warns about by recommending every
     * organization keep more than one.
     */
    public function isLastActiveOwner(): bool
    {
        if ($this->role !== MembershipRole::Owner || $this->status !== MembershipStatus::Active) {
            return false;
        }

        return $this->company->memberships()
            ->where('role', MembershipRole::Owner)
            ->where('status', MembershipStatus::Active)
            ->count() === 1;
    }
}
