<?php

namespace App\Actions;

use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Notifications\JobAlertMatches;
use Illuminate\Support\Facades\DB;

/**
 * One alert's turn: find the postings that match it and that it has not
 * sent yet, email them, and remember them.
 *
 * Only postings published since the alert was made count -- the ones
 * before it were on screen when the candidate saved the search. An empty
 * run still moves last_sent_at, which is "last checked": a weekly alert
 * waits a week either way, rather than turning daily until something
 * matches.
 */
class SendJobAlert
{
    /**
     * The email lists this many; the rest are one link away.
     */
    public const LISTED_PER_EMAIL = 10;

    public function __invoke(JobAlert $jobAlert): int
    {
        $matches = JobPosting::query()
            ->active()
            ->matching($jobAlert->criteria)
            ->where('published_at', '>=', $jobAlert->created_at)
            ->whereDoesntHave('jobAlerts', fn ($query) => $query->whereKey($jobAlert->id))
            ->with('company:id,name')
            ->latest('published_at')
            ->latest('id')
            ->get();

        if ($matches->isNotEmpty()) {
            $jobAlert->user->notify(new JobAlertMatches(
                $jobAlert,
                $matches->take(self::LISTED_PER_EMAIL),
                $matches->count(),
            ));
        }

        // Recorded only after the mail went out: if sending throws, the
        // next run tries the same postings again instead of skipping them.
        DB::transaction(function () use ($jobAlert, $matches) {
            $jobAlert->sentJobPostings()->attach($matches->modelKeys());
            $jobAlert->forceFill(['last_sent_at' => now()])->save();
        });

        return $matches->count();
    }
}
