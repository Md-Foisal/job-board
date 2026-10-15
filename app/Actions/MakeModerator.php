<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Enums\StaffRole;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A super admin bringing someone onto the moderation team. The panel only
 * ever hands out this role: the super admin role comes from the server
 * (staff:grant), so a stolen panel session cannot create another one.
 */
class MakeModerator
{
    public function __invoke(User $user, User $staff): ModerationEvent
    {
        return DB::transaction(function () use ($user, $staff) {
            $user->staff_role = StaffRole::Moderator;
            $user->save();

            return $user->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::GrantModerator,
            ]);
        });
    }
}
