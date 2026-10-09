<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\PublicCache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The platform as a whole: how much is on it, and how much it is used.
 * How the moderation itself is going sits with the queues, above.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * Rendered with the page rather than after it: it sits at the top,
     * where a lazy widget shows an empty box first and then pushes the
     * rest of the dashboard down when it arrives.
     */
    protected static bool $isLazy = false;

    protected ?string $heading = 'Platform';

    /**
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        return ['@md' => 2, '@4xl' => 4];
    }

    protected function getStats(): array
    {
        // Counts across the largest tables, identical for every member of
        // staff. The queue widget above stays live: that is the one a
        // moderator acts on.
        $numbers = PublicCache::remember('admin-platform-numbers', fn () => [
            'live' => JobPosting::query()->active()->count(),
            'companies' => Company::count(),
            'verified' => Company::whereNotNull('verified_at')->count(),
            'people' => User::count(),
            'applications' => Application::where('created_at', '>=', now()->subWeek())->count(),
        ]);

        return [
            Stat::make('Live job postings', $numbers['live']),
            Stat::make('Companies', $numbers['companies'])
                ->description($numbers['verified'].' verified'),
            Stat::make('People', $numbers['people']),
            Stat::make('Applications this week', $numbers['applications']),
        ];
    }
}
