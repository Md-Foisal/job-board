<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's public face as a recruiter -- the name, photo and short
 * introduction a candidate sees next to a job posting.
 *
 * It hangs off the User rather than off a Membership because the person
 * is the same person whichever company they are posting for; an agency
 * recruiter working for three clients still shows one face. Which
 * company a posting belongs to is answered by the posting itself, not
 * here. It is optional -- without one, a candidate simply sees the
 * company name and nothing more.
 */
#[Fillable(['user_id', 'display_name', 'avatar_path', 'bio'])]
class RecruiterProfile extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
