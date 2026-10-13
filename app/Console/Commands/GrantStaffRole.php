<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\select;

/**
 * How the platform gets its first super admin, and any later one: whoever
 * holds the keys to the server and its database is the owner, and the
 * owner hands out the top role from here -- never from the admin panel,
 * where a stolen staff session could hand it to anyone. A super admin can
 * then make moderators in the panel.
 *
 * It promotes an account that already exists, so the person signs up the
 * ordinary way first and their email address is known to work.
 */
#[Signature('staff:grant {email : The email address of an existing account} {--role= : moderator or super_admin}')]
#[Description('Give an existing account a staff role')]
class GrantStaffRole extends Command
{
    public function handle(): int
    {
        $user = User::withTrashed()->where('email', $this->argument('email'))->first();

        if ($problem = $this->whyNot($user)) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        $role = $this->role();

        if ($role === null) {
            $this->components->error('The role must be moderator or super_admin.');

            return self::FAILURE;
        }

        if ($user->staff_role === $role) {
            $this->components->info("{$user->email} is already a {$role->label()}.");

            return self::SUCCESS;
        }

        $previous = $user->staff_role;
        $user->staff_role = $role;
        $user->save();

        Log::notice('Staff role granted from the command line.', [
            'user_id' => $user->id,
            'role' => $role->value,
            'previous_role' => $previous?->value,
        ]);

        $this->components->info("{$user->email} is now a {$role->label()}.");

        if ($user->two_factor_confirmed_at === null) {
            $this->components->warn('The staff panel opens once they set up two-factor authentication in their security settings.');
        }

        return self::SUCCESS;
    }

    /**
     * The same conditions the panel applies when a super admin makes a
     * moderator: a live account whose owner can be reached, and a password
     * -- staff actions ask for it again before anything is taken away.
     */
    private function whyNot(?User $user): ?string
    {
        return match (true) {
            $user === null => 'No account uses that email address. They need to sign up first.',
            $user->anonymized_at !== null => 'That account has been erased.',
            $user->trashed() => 'That account has been deleted by its owner.',
            $user->account_status !== AccountStatus::Active => 'That account is suspended. Reinstate it first.',
            ! $user->hasVerifiedEmail() => 'That account has not verified its email address yet.',
            ! $user->hasPassword() => 'That account has no password yet (it signs in with Google or GitHub). They can add one in their security settings.',
            default => null,
        };
    }

    private function role(): ?StaffRole
    {
        $role = $this->option('role');

        if ($role === null && $this->input->isInteractive()) {
            $role = select(
                label: 'Which role?',
                options: [StaffRole::Moderator->value => StaffRole::Moderator->label(), StaffRole::SuperAdmin->value => StaffRole::SuperAdmin->label()],
            );
        }

        return StaffRole::tryFrom((string) $role);
    }
}
