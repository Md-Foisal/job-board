<?php

namespace App\Models;

use App\Enums\SignInProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Google or GitHub account its owner signs in with. Recognised by the
 * provider's id; the email is kept only to show which account it is.
 */
#[Fillable(['provider', 'provider_user_id', 'email'])]
class ConnectedAccount extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => SignInProvider::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
