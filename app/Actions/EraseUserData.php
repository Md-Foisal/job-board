<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Models\ModerationEvent;
use App\Models\User;

/**
 * Erasing a person's data now, because they asked -- by email, say --
 * rather than waiting out the grace period that follows a self-service
 * deletion. GDPR asks for erasure "without undue delay", and thirty days
 * of waiting on a request already made is exactly that.
 *
 * The decision goes on the moderation trail like any other, and the
 * erasure runs first: a trail entry for an erasure that failed would
 * claim something that did not happen.
 */
class EraseUserData
{
    public function __construct(private AnonymizeUser $anonymizeUser) {}

    public function __invoke(User $user, User $staff, string $reason): ModerationEvent
    {
        ($this->anonymizeUser)($user);

        return $user->moderationEvents()->create([
            'admin_id' => $staff->id,
            'action' => ModerationAction::EraseUser,
            'reason' => $reason,
        ]);
    }
}
