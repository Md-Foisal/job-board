<?php

namespace App\Console\Commands;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marks pending invitations whose link has lapsed as expired.
 *
 * Accepting one already checks the date, so a lapsed link never lets
 * anyone in; this only makes the stored status say what is true.
 */
#[Signature('invitations:expire')]
#[Description('Mark pending team invitations past their expiry as expired')]
class ExpireInvitations extends Command
{
    public function handle(): int
    {
        $expired = Invitation::query()
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatus::Expired]);

        $this->components->info("Expired {$expired} ".str('invitation')->plural($expired).'.');

        return self::SUCCESS;
    }
}
