<?php

namespace App\Policies;

use App\Models\ExperienceRecord;
use App\Models\User;

class ExperienceRecordPolicy
{
    /**
     * Owner-only, same shape as EducationRecordPolicy::update -- a candidate
     * may only touch experience records that belong to their own
     * CandidateProfile. The Livewire component already scopes its
     * queries through that relation, but this Policy is the actual
     * enforcement: a wire:click payload is just a request parameter the
     * client sends, so the id it names still has to be checked here.
     */
    public function update(User $user, ExperienceRecord $experienceRecord): bool
    {
        return $user->candidateProfile?->id === $experienceRecord->candidate_profile_id;
    }

    public function delete(User $user, ExperienceRecord $experienceRecord): bool
    {
        return $this->update($user, $experienceRecord);
    }
}
