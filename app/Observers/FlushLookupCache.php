<?php

namespace App\Observers;

use App\Support\PublicCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Skills and categories feed every filter and posting form; adding,
 * renaming, merging or removing one moves both the lookup lists and the
 * public pages that show category names.
 */
class FlushLookupCache implements ShouldHandleEventsAfterCommit
{
    public function saved(): void
    {
        PublicCache::flushLookups();
    }

    public function deleted(): void
    {
        PublicCache::flushLookups();
    }

    public function restored(): void
    {
        PublicCache::flushLookups();
    }
}
