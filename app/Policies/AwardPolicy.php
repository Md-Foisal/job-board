<?php

namespace App\Policies;

use App\Models\Award;
use App\Models\User;

class AwardPolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, Award $entry): bool
    {
        return $user->candidateProfile?->id === $entry->candidate_profile_id;
    }

    public function delete(User $user, Award $entry): bool
    {
        return $this->update($user, $entry);
    }
}
