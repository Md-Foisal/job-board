<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Taking the moderator role away. The account itself is untouched; the
 * panel closes to it on its next request, and every decision it took
 * stays in the log under its name.
 */
class RemoveModerator
{
    public function __invoke(User $user, User $staff, string $reason): ModerationEvent
    {
        return DB::transaction(function () use ($user, $staff, $reason) {
            $user->staff_role = null;
            $user->save();

            return $user->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RevokeModerator,
                'reason' => $reason,
            ]);
        });
    }
}
