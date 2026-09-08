<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Membership;
use App\Models\Invitation;
use App\Models\JobPosting;
use App\Enums\IdentityType;
use App\Enums\AccountStatus;

#[Fillable(['name', 'slug', 'identity_type', 'description', 'website_url', 'logo_path', 'size', 'industry'])]
class Company extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
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
}
