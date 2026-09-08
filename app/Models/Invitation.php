<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Company;
use App\Enums\MembershipRole;
use App\Enums\InvitationStatus;

#[Fillable(['company_id', 'invited_by_id', 'email', 'role', 'token', 'status', 'expires_at'])]
class Invitation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
