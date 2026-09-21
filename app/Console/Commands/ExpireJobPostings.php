<?php

namespace App\Console\Commands;

use App\Enums\AvailabilityStatus;
use App\Models\JobPosting;
use App\Support\PublicCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marks open postings whose closing date has passed as expired.
 *
 * Candidates never depend on this: every public query already compares
 * expires_at with the clock, so a lapsed posting drops out of search and
 * its page answers 404 the moment its date passes. What the stored status
 * drives is the company's own view -- the "Expired" badge and filter, and
 * whether the row offers Close or Reopen -- which is why it runs every
 * minute rather than once a day.
 */
#[Signature('job-postings:expire')]
#[Description('Mark open job postings past their closing date as expired')]
class ExpireJobPostings extends Command
{
    public function handle(): int
    {
        // One UPDATE, so no model events: the public cache that the
        // posting observer would have flushed is flushed by hand below.
        $expired = JobPosting::query()
            ->where('availability_status', AvailabilityStatus::Active)
            ->where('expires_at', '<=', now())
            ->update(['availability_status' => AvailabilityStatus::Expired]);

        if ($expired > 0) {
            PublicCache::flushPublic();
        }

        $this->components->info("Expired {$expired} job ".str('posting')->plural($expired).'.');

        return self::SUCCESS;
    }
}
