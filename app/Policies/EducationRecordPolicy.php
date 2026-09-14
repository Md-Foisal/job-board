<?php

namespace App\Policies;

use App\Models\EducationRecord;
use App\Models\User;

class EducationRecordPolicy
{
    /**
     * Owner-only, same shape as ApplicationPolicy::view -- a candidate
     * may only touch education records that belong to their own
     * CandidateProfile. The Livewire component already scopes its
     * queries through that relation, but this Policy is the actual
     * enforcement: a wire:click payload is just a request parameter the
     * client sends, so the id it names still has to be checked here.
     */
    public function update(User $user, EducationRecord $educationRecord): bool
    {
        return $user->candidateProfile?->id === $educationRecord->candidate_profile_id;
    }

    public function delete(User $user, EducationRecord $educationRecord): bool
    {
        return $this->update($user, $educationRecord);
    }
}
