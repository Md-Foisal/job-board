<?php

namespace App\Observers;

use App\Support\PublicCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Any saved or removed posting, company or report can change what a
 * visitor sees -- a posting going live, a company being banned, a third
 * report hiding something -- so each one moves the public cache on.
 *
 * It runs after the surrounding transaction commits. Flushing inside it
 * would let another request rebuild the cache from the old rows in the
 * moment before the new ones become visible.
 *
 * Bulk query updates fire no model events, so code that changes these
 * tables that way flushes explicitly.
 */
class FlushPublicCache implements ShouldHandleEventsAfterCommit
{
    public function saved(): void
    {
        PublicCache::flushPublic();
    }

    public function deleted(): void
    {
        PublicCache::flushPublic();
    }

    public function restored(): void
    {
        PublicCache::flushPublic();
    }
}
