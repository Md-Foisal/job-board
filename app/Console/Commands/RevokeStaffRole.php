<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Takes a staff role away from the server side -- the only way to remove
 * a super admin, since the panel never touches that role. The account
 * itself stays; it simply stops being staff, and the panel closes to it
 * on its next request.
 */
#[Signature('staff:revoke {email : The email address of the staff account}')]
#[Description('Take the staff role away from an account')]
class RevokeStaffRole extends Command
{
    public function handle(): int
    {
        $user = User::withTrashed()->where('email', $this->argument('email'))->first();

        if ($user === null || $user->staff_role === null) {
            $this->components->error('No staff account uses that email address.');

            return self::FAILURE;
        }

        if ($this->isLastSuperAdmin($user)
            && ! $this->confirm('This is the last active super admin. Nobody will be able to manage moderators until another is granted. Continue?', false)) {
            $this->components->warn('Nothing changed.');

            return self::FAILURE;
        }

        $previous = $user->staff_role;
        $user->staff_role = null;
        $user->save();

        Log::notice('Staff role revoked from the command line.', [
            'user_id' => $user->id,
            'previous_role' => $previous->value,
        ]);

        $this->components->info("{$user->email} is no longer a {$previous->label()}.");

        return self::SUCCESS;
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin()
            && ! User::query()
                ->where('staff_role', StaffRole::SuperAdmin)
                ->where('account_status', AccountStatus::Active)
                ->whereKeyNot($user->id)
                ->exists();
    }
}
