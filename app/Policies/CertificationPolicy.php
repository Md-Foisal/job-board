<?php

namespace App\Policies;

use App\Models\Certification;
use App\Models\User;

class CertificationPolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, Certification $certification): bool
    {
        return $user->candidateProfile?->id === $certification->candidate_profile_id;
    }

    public function delete(User $user, Certification $certification): bool
    {
        return $this->update($user, $certification);
    }
}
