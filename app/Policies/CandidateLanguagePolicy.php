<?php

namespace App\Policies;

use App\Models\CandidateLanguage;
use App\Models\User;

class CandidateLanguagePolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, CandidateLanguage $entry): bool
    {
        return $user->candidateProfile?->id === $entry->candidate_profile_id;
    }

    public function delete(User $user, CandidateLanguage $entry): bool
    {
        return $this->update($user, $entry);
    }
}
