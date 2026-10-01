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
 * it leaves out what would inflate it: crawlers, pages a browser fetched in
 * advance, the company's own people looking at their posting, platform
 * staff, a posting the public cannot see, and the same browser session
 * opening the same job again on the same day. The day is the company's,
 * in its own time zone, as everything on its analytics page is.
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
     * Postings this session has already been counted for, each with the
     * day it was counted on. Per posting rather than one day for the whole
     * list, because two companies' days need not be the same day.
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

        $date = today($jobPosting->company->timezone)->toDateString();

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

        if ($this->isSpeculative($request)) {
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
     * A browser can fetch a page before anyone asks for it: Chrome
     * prefetches or prerenders on its own from the address bar when it
     * expects the visit, and on bookmark hover. Such a request says so in
     * Sec-Purpose ("prefetch", or "prefetch;prerender"), and the visitor
     * may never open the page.
     *
     * The cost is that a prerendered page the visitor does open is not
     * counted, because opening it sends no second request. Counting it
     * would take a script reporting the moment the page is shown. A
     * slight undercount is the safer error: an employer deciding whether
     * a posting works should not be shown views that never happened.
     */
    private function isSpeculative(Request $request): bool
    {
        $purposes = explode(',', (string) $request->header('Sec-Purpose'));

        foreach ($purposes as $purpose) {
            if (strtolower(trim(explode(';', $purpose)[0])) === 'prefetch') {
                return true;
            }
        }

        return false;
    }

    /**
     * Marks the posting as counted for this session today, and says
     * whether it already was.
     */
    private function alreadyCounted(JobPosting $jobPosting, Request $request, string $date): bool
    {
        // Every zone's today lies within a day of UTC's, so anything older
        // than the day before yesterday can no longer match and is dropped.
        $stale = today()->subDays(2)->toDateString();

        $counted = collect($request->session()->get(self::SESSION_KEY, []))
            ->filter(fn ($day, $jobId) => is_int($jobId) && is_string($day) && $day >= $stale);

        if ($counted->get($jobPosting->id) === $date) {
            return true;
        }

        $request->session()->put(self::SESSION_KEY, $counted->put($jobPosting->id, $date)->all());

        return false;
    }

    /**
     * One statement, so two readers arriving at once can never both read
     * the old count. The existing value is named with its table: in the
     * conflict branch PostgreSQL also has the proposed row in scope, and
     * an unqualified column would be ambiguous there.
     */
    private function increment(JobPosting $jobPosting, string $date): void
    {
        $table = (new JobPostingDailyStat)->getTable();

        JobPostingDailyStat::query()->upsert(
            [['job_posting_id' => $jobPosting->id, 'date' => $date, 'views' => 1]],
            ['job_posting_id', 'date'],
            ['views' => DB::raw($table.'.views + 1')],
        );
    }
}
