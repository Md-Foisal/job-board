<?php

namespace App\Filament\Widgets;

use App\Enums\ReportStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use App\Support\PublicCache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The platform as a whole, and the last week of moderation.
 *
 * The action rate -- of the reports closed this week, how many led to
 * something being done -- is the check on the queue itself: close to zero
 * means reporting is mostly noise; close to all means bad actors are
 * getting through review and being caught only by users.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Platform';

    protected function getStats(): array
    {
        // Seven counts across the largest tables, identical for every
        // member of staff. The queue widget above stays live: that is the
        // one a moderator acts on.
        $numbers = PublicCache::remember('admin-platform-numbers', function () {
            $weekAgo = now()->subWeek();

            $closed = Report::query()
                ->where('review_status', '!=', ReportStatus::Pending->value)
                ->where('updated_at', '>=', $weekAgo);

            return [
                'live' => JobPosting::query()->active()->count(),
                'companies' => Company::count(),
                'verified' => Company::whereNotNull('verified_at')->count(),
                'people' => User::count(),
                'applications' => Application::where('created_at', '>=', $weekAgo)->count(),
                'decisions' => ModerationEvent::where('created_at', '>=', $weekAgo)->count(),
                'closed' => (clone $closed)->count(),
                'actioned' => (clone $closed)->where('review_status', ReportStatus::Actioned->value)->count(),
            ];
        });

        return [
            Stat::make('Live job postings', $numbers['live']),
            Stat::make('Companies', $numbers['companies'])
                ->description($numbers['verified'].' verified'),
            Stat::make('People', $numbers['people']),
            Stat::make('Applications this week', $numbers['applications']),
            Stat::make('Moderation decisions this week', $numbers['decisions'])
                ->description($numbers['closed'] > 0
                    ? round($numbers['actioned'] / $numbers['closed'] * 100).'% of closed reports led to action'
                    : 'No reports closed this week'),
        ];
    }
}
