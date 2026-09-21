<?php

namespace App\Filament\Widgets;

use App\Enums\ReportStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
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
        $weekAgo = now()->subWeek();

        $closed = Report::query()
            ->where('review_status', '!=', ReportStatus::Pending->value)
            ->where('updated_at', '>=', $weekAgo);
        $closedCount = (clone $closed)->count();
        $actioned = (clone $closed)->where('review_status', ReportStatus::Actioned->value)->count();

        return [
            Stat::make('Live job postings', JobPosting::query()->active()->count()),
            Stat::make('Companies', Company::count())
                ->description(Company::whereNotNull('verified_at')->count().' verified'),
            Stat::make('People', User::count()),
            Stat::make('Applications this week', Application::where('created_at', '>=', $weekAgo)->count()),
            Stat::make('Moderation decisions this week', ModerationEvent::where('created_at', '>=', $weekAgo)->count())
                ->description($closedCount > 0
                    ? round($actioned / $closedCount * 100).'% of closed reports led to action'
                    : 'No reports closed this week'),
        ];
    }
}
