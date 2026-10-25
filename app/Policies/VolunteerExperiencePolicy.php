<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolunteerExperience;

class VolunteerExperiencePolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, VolunteerExperience $entry): bool
    {
        return $user->candidateProfile?->id === $entry->candidate_profile_id;
    }

    public function delete(User $user, VolunteerExperience $entry): bool
    {
        return $this->update($user, $entry);
    }
}
