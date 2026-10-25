<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, Publication $entry): bool
    {
        return $user->candidateProfile?->id === $entry->candidate_profile_id;
    }

    public function delete(User $user, Publication $entry): bool
    {
        return $this->update($user, $entry);
    }
}
