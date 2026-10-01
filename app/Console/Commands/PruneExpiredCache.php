<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes cache entries whose time is up, when the cache lives in the
 * database.
 *
 * The database store only removes an expired entry when that same key is
 * read again, and some never are: an AI explanation is kept under a key
 * tied to the profile as it was, so once the profile changes the old key
 * is never asked for. Those entries hold what the AI wrote about a person
 * or read from their CV, and they must go when their time is up, not sit
 * in the table for good. Redis expires keys itself, so there it has
 * nothing to do.
 */
#[Signature('cache:prune-expired')]
#[Description('Delete expired entries from the database cache store')]
class PruneExpiredCache extends Command
{
    public function handle(): int
    {
        $store = config('cache.stores.'.config('cache.default'));

        if (($store['driver'] ?? null) !== 'database') {
            $this->components->info('The cache store expires entries itself; nothing to prune.');

            return self::SUCCESS;
        }

        $pruned = DB::connection($store['connection'] ?? null)
            ->table($store['table'] ?? 'cache')
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();

        $this->components->info("Pruned {$pruned} expired cache ".str('entry')->plural($pruned).'.');

        return self::SUCCESS;
    }
}
