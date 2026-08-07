<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

use App\Models\Role;
use App\Models\JobListing;
use App\Models\Application;
use App\Models\Organization;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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

    public function roles() 
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('assigned_at', 'assigned_by', 'expires_at', 'is_active')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
        ->where('name', $role)
        ->wherePivot('is_active', true)
        ->where(function ($query) {
            $query->whereNull('role_user.expires_at')
                  ->orWhere('role_user.expires_at', '>', now());
        })
        ->exists();
    }

    public function jobListings()
    {
        return $this->hasMany(JobListing::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function organization()
    {
    return $this->hasOne(Organization::class);
    }
}