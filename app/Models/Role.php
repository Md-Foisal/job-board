<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

use App\Models\User;

#[Fillable(['name'])]
class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('assigned_at', 'assigned_by', 'expires_at', 'is_active')
            ->withTimestamps();
    }
}
