<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\User;

class ApplicationNotePolicy
{
    /**
     * Reading and writing notes is open to anyone currently working at
     * the company that posted the job -- reviewing applications is a
     * team activity, not something reserved for owners and managers.
     */
    public function viewAny(User $user, Application $application): bool
    {
        return $user->worksAt($application->jobPosting->company);
    }

    public function create(User $user, Application $application): bool
    {
        return $this->viewAny($user, $application);
    }

    /**
     * Editing is narrower than writing: a note is one person's own
     * assessment, so only its author may rewrite or remove it, and only
     * while they still work there -- losing the membership closes the
     * door the same way it closes every other company door.
     */
    public function update(User $user, ApplicationNote $note): bool
    {
        return $user->id === $note->author_id
            && $user->worksAt($note->application->jobPosting->company);
    }

    public function delete(User $user, ApplicationNote $note): bool
    {
        return $this->update($user, $note);
    }
}
