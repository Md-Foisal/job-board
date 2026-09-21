<?php

namespace App\Filament\Widgets;

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Company;
use App\Models\Report;
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

    protected function getStats(): array
    {
        $postings = JobPostingResource::getEloquentQuery()
            ->where('moderation_status', ModerationStatus::Pending->value);

        $companies = Company::query()
            ->whereNull('verified_at')
            ->where('account_status', AccountStatus::Active->value);

        return [
            $this->queueStat(
                'Job postings to review',
                $postings->count(),
                $postings->min('published_at'),
                JobPostingResource::getUrl('index'),
                Heroicon::OutlinedRectangleStack,
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
