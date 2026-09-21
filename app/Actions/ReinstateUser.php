<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifting a suspension. Everything the person had is exactly as it was.
 */
class ReinstateUser
{
    public function __invoke(User $user, User $staff, ?string $note = null): ModerationEvent
    {
        return DB::transaction(function () use ($user, $staff, $note) {
            $user->account_status = AccountStatus::Active;
            $user->save();

            return $user->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::ReinstateUser,
                'reason' => $note,
            ]);
        });
    }
}
