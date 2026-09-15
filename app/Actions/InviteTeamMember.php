<?php

namespace App\Actions;

use App\Enums\InvitationStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\TeamMemberInvited;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteTeamMember
{
    /**
     * The invitation is a row rather than a bare signed link because the
     * people who sent it need to see it: a roster that cannot show who is
     * still pending, or take an invitation back, is a roster with a blind
     * spot.
     *
     * The address is notified directly instead of through a user, since
     * the whole point is inviting someone who may not have an account yet.
     */
    public function __invoke(Company $company, User $invitedBy, string $email, string $role): Invitation
    {
        $invitation = $company->invitations()->create([
            'invited_by_id' => $invitedBy->id,
            'email' => Str::lower($email),
            'role' => $role,
            'token' => Str::random(64),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addWeek(),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamMemberInvited($invitation));

        return $invitation;
    }
}
