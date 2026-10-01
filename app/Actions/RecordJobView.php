<?php

namespace App\Actions;

use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\JobView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

use function Illuminate\Support\defer;

/**
 * Opening a job's page leaves two different traces.
 *
 * A signed-in candidate gets the posting on their "recently viewed" list:
 * one row per (user, job), upserted so a repeat visit only moves
 * viewed_at forward.
 *
 * Separately, the posting's view count for the day goes up by one -- for
 * anyone, guests included, because most people reading a job have not
 * signed in. The count is what the employer's analytics are built on, so
 * it leaves out what would inflate it: crawlers, the company's own people
 * looking at their posting, platform staff, a posting the public cannot
 * see, and the same browser session opening the same job again on the
 * same day.
 *
 * The decision is made during the request, because it needs the session,
 * which is saved before the response goes out. The write itself waits
 * until the response has been sent, so the page is never slower for it,
 * and a failed write is logged rather than shown to the visitor, who has
 * already got their page.
 */
class RecordJobView
{
    /**
     * Postings this session has already been counted for, and the day
     * that list belongs to; a new day starts a new list.
     */
    private const SESSION_KEY = 'job_views_counted';

    public function __construct(private CrawlerDetect $crawlerDetect) {}

    public function __invoke(JobPosting $jobPosting, Request $request): void
    {
        $user = $request->user();

        if ($user?->isCandidate()) {
            JobView::updateOrCreate(
                ['user_id' => $user->id, 'job_posting_id' => $jobPosting->id],
                ['viewed_at' => now()],
            );
        }

        $date = today()->toDateString();

        if (! $this->counts($jobPosting, $request) || $this->alreadyCounted($jobPosting, $request, $date)) {
            return;
        }

        defer(fn () => rescue(fn () => $this->increment($jobPosting, $date)));
    }

    private function counts(JobPosting $jobPosting, Request $request): bool
    {
        // A HEAD request asks about the page without receiving it, so
        // nobody has read anything.
        if (! $request->isMethod('GET')) {
            return false;
        }

        $user = $request->user();

        if ($user !== null && ($user->isStaff() || $user->worksAt($jobPosting->company))) {
            return false;
        }

        // Every browser sends a user agent. A request without one is a
        // script, and CrawlerDetect would find nothing to match in it.
        $userAgent = (string) $request->userAgent();

        if ($userAgent === '' || $this->crawlerDetect->isCrawler($userAgent)) {
            return false;
        }

        return $jobPosting->isPubliclyVisible();
    }

    /**
     * Marks the posting as counted for this session today, and says
     * whether it already was.
     */
    private function alreadyCounted(JobPosting $jobPosting, Request $request, string $date): bool
    {
        $counted = $request->session()->get(self::SESSION_KEY);

        $jobIds = ($counted['date'] ?? null) === $date ? $counted['jobs'] : [];

        if (in_array($jobPosting->id, $jobIds, true)) {
            return true;
        }

        $jobIds[] = $jobPosting->id;

        $request->session()->put(self::SESSION_KEY, ['date' => $date, 'jobs' => $jobIds]);

        return false;
    }

    private function increment(JobPosting $jobPosting, string $date): void
    {
        JobPostingDailyStat::query()->upsert(
            [['job_posting_id' => $jobPosting->id, 'date' => $date, 'views' => 1]],
            ['job_posting_id', 'date'],
            ['views' => DB::raw('views + 1')],
        );
    }
}
