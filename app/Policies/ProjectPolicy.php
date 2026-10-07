<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Owner-only, like the other parts of a profile: the id in a
     * wire:click is whatever the browser sends, so it is checked here.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->candidateProfile?->id === $project->candidate_profile_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
