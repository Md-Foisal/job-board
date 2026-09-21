<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Barring a person from the platform. Nothing they made is removed --
 * applications, notes and postings stay, as the record needs them to --
 * they simply cannot sign in, and are signed out on their next request.
 *
 * This is deliberately a different act from someone deleting their own
 * account: a suspension is the platform's decision, and nothing the
 * person can do on their own lifts it.
 */
class SuspendUser
{
    public function __invoke(User $user, User $staff, string $reason): ModerationEvent
    {
        return DB::transaction(function () use ($user, $staff, $reason) {
            $user->account_status = AccountStatus::Suspended;
            $user->save();

            return $user->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::SuspendUser,
                'reason' => $reason,
            ]);
        });
    }
}
