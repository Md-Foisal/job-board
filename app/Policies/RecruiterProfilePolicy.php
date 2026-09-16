<?php

namespace App\Policies;

use App\Models\RecruiterProfile;
use App\Models\User;

class RecruiterProfilePolicy
{
    /**
     * A recruiter profile is a singleton belonging to one user, so only
     * that user may change it. There is no view ability here: the profile
     * is deliberately public -- it exists to be shown to candidates
     * deciding whether to apply.
     */
    public function update(User $user, RecruiterProfile $recruiterProfile): bool
    {
        return $user->id === $recruiterProfile->user_id;
    }

    public function delete(User $user, RecruiterProfile $recruiterProfile): bool
    {
        return $this->update($user, $recruiterProfile);
    }
}
