<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Owner-only, same shape as EducationRecordPolicy::update -- a candidate
     * may only touch documents that belong to their own CandidateProfile.
     * Used for the "replace" action (swap the file behind an existing
     * Document row -- see ReplaceDocument).
     */
    public function update(User $user, Document $document): bool
    {
        return $user->candidateProfile?->id === $document->candidate_profile_id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Gates the download route (claude/14 step 7 security fix -- a
     * Policy-checked route, not a guessable public-disk URL, is what
     * actually keeps a CV private).
     */
    public function download(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
