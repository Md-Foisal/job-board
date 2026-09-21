<?php

namespace App\Console\Commands;

use App\Actions\AnonymizeUser;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Erases accounts whose owners deleted them and did not come back within
 * the grace period. Until then the account is only switched off and can
 * be restored; after it, there is nothing left to restore.
 */
#[Signature('users:anonymize-deleted')]
#[Description('Erase the personal data of accounts deleted more than the grace period ago')]
class AnonymizeDeletedUsers extends Command
{
    public function handle(AnonymizeUser $anonymizeUser): int
    {
        $erased = 0;
        $failed = 0;

        User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->where('deleted_at', '<=', now()->subDays(User::DELETION_GRACE_DAYS))
            ->chunkById(100, function ($users) use ($anonymizeUser, &$erased, &$failed) {
                foreach ($users as $user) {
                    try {
                        $anonymizeUser($user);
                        $erased++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->components->info("Erased {$erased} ".str('account')->plural($erased).", {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
