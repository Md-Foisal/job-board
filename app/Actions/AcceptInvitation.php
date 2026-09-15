<?php

namespace App\Actions;

use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    /**
     * Joining a company and closing the invitation are one act: leave the
     * invitation open and the same link works twice.
     *
     * Someone who once worked here and was let go still has their old
     * membership row, so accepting reactivates that row rather than
     * adding a second one -- the table allows only one membership per
     * person per company, and their history is worth keeping either way.
     */
    public function __invoke(Invitation $invitation, User $user): Membership
    {
        return DB::transaction(function () use ($invitation, $user) {
            $membership = Membership::firstOrNew([
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
            ]);

            $membership->role = $invitation->role;
            $membership->status = MembershipStatus::Active;
            $membership->save();

            $invitation->update(['status' => InvitationStatus::Accepted]);

            return $membership;
        });
    }
}
