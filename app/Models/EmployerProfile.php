<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\JobListing;

#[Fillable(['user_id', 'name', 'identity_type', 'logo', 'cover_photo', 'website', 'about', 'founded_year', 'location'])]
class EmployerProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
        ];
    }

    /**
     * The name to show for this employer everywhere (job card, job detail,
     * apply form, etc.) instead of asking for a free-text "company name"
     * on every job post.
     */
    public function displayName(): string
    {
        return $this->name;
    }

    /**
     * The image to show for this employer everywhere a logo/avatar is
     * needed. Falls back to the underlying user's avatar for individual
     * employers (or any company that hasn't uploaded a logo yet).
     */
    public function displayImage(): ?string
    {
        return $this->logo ?? $this->user?->avatar;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobListings()
    {
        return $this->hasMany(JobListing::class);
    }
}
