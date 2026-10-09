<?php

namespace App\Filament\Widgets;

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\CompanyReviews\CompanyReviewResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Support\PublicCache;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What is waiting for a decision, one card per queue, each a link into it.
 *
 * Every card says how long the oldest item has been waiting, not just how
 * many there are: ten reports from this morning and one from last week
 * are very different problems, and "time from report to decision" is the
 * number trust and safety teams answer for.
 */
class ModerationQueuesOverview extends StatsOverviewWidget
{
    /**
     * How long an item may wait before its card turns red. Job boards
     * that review by hand promise a decision within hours, not days.
     */
    public const TARGET_HOURS = 24;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Waiting on you';

    /**
     * Four queues, so the cards fill their rows: four across on a desktop,
     * two on a tablet, one on a phone. Reviews and the companies' answers
     * to them are one queue here, as they are in the menu, since both are
     * decided on the same page.
     *
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        return ['@md' => 2, '@4xl' => 4];
    }

    /**
     * How the queues are being worked, under the heading: the decisions of
     * the last week, and the action rate -- of the reports closed this
     * week, how many led to something being done. Close to zero means
     * reporting is mostly noise; close to all means bad actors are getting
     * through review and being caught only by users.
     */
    protected function getDescription(): ?string
    {
        $numbers = PublicCache::remember('admin-moderation-week', function () {
            $weekAgo = now()->subWeek();

            $closed = Report::query()
                ->where('review_status', '!=', ReportStatus::Pending->value)
                ->where('updated_at', '>=', $weekAgo);

            return [
                'decisions' => ModerationEvent::where('created_at', '>=', $weekAgo)->count(),
                'closed' => (clone $closed)->count(),
                'actioned' => (clone $closed)->where('review_status', ReportStatus::Actioned->value)->count(),
            ];
        });

        $decisions = trans_choice('{0} No decisions this week|{1} 1 decision this week|[2,*] :count decisions this week', $numbers['decisions']);

        return $decisions.' · '.($numbers['closed'] > 0
            ? round($numbers['actioned'] / $numbers['closed'] * 100).'% of closed reports led to action'
            : 'no reports closed');
    }

    protected function getStats(): array
    {
        $postings = JobPostingResource::getEloquentQuery()->awaitingReview();

        $reviews = CompanyReviewResource::getEloquentQuery();
        $reviewsWaiting = (clone $reviews)->where('moderation_status', ModerationStatus::Pending->value);
        $answersWaiting = (clone $reviews)->where('response_status', ModerationStatus::Pending->value);

        $oldestReview = collect([
            $reviewsWaiting->min('updated_at'),
            $answersWaiting->min('responded_at'),
        ])->filter()->min();

        $companies = Company::query()
            ->whereNull('verified_at')
            ->where('account_status', AccountStatus::Active->value);

        return [
            $this->queueStat(
                'Job postings to review',
                $postings->count(),
                $postings->min('submitted_at'),
                JobPostingResource::getUrl('index'),
                Heroicon::OutlinedRectangleStack,
            ),
            $this->queueStat(
                'Reviews and answers to check',
                // The same count as the menu badge: a review whose answer is
                // also waiting is one row to open, not two.
                CompanyReviewResource::waitingCount(),
                $oldestReview,
                // Straight to the tab that has something in it.
                $reviewsWaiting->exists()
                    ? CompanyReviewResource::getUrl('index')
                    : CompanyReviewResource::getUrl('index', ['tab' => 'responses']),
                Heroicon::OutlinedChatBubbleLeftRight,
            ),
            $this->queueStat(
                'Reported things',
                ReportResource::openSubjectCount(),
                Report::query()->where('review_status', ReportStatus::Pending->value)->min('created_at'),
                ReportResource::getUrl('index'),
                Heroicon::OutlinedFlag,
            ),
            $this->queueStat(
                'Companies not verified',
                $companies->count(),
                $companies->min('created_at'),
                CompanyResource::getUrl('index'),
                Heroicon::OutlinedBuildingOffice2,
            ),
        ];
    }

    private function queueStat(string $label, int $count, CarbonInterface|string|null $oldest, string $url, Heroicon $icon): Stat
    {
        $stat = Stat::make($label, $count)->icon($icon)->url($url);

        if ($count === 0 || $oldest === null) {
            return $stat->description('All clear')->color('success');
        }

        $oldest = now()->parse($oldest);

        return $stat
            ->description('Oldest waiting '.$oldest->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE))
            ->descriptionIcon(Heroicon::OutlinedClock)
            ->color($oldest->diffInHours(now()) >= self::TARGET_HOURS ? 'danger' : 'warning');
    }
}
