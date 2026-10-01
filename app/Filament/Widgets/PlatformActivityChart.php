<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Day by day, how much the platform is used: job views, applications, or
 * postings going live, one at a time.
 *
 * One series at a time rather than three on one chart: views run in the
 * thousands where postings run in single figures, and on a shared axis
 * the small lines would sit flat along the bottom. The filter picks which.
 *
 * Days are UTC, the app's timezone, as on the employer analytics page.
 * The counts are cached for ten minutes, the same as the employers'
 * numbers, so the chart does not poll; a reload shows anything newer.
 */
class PlatformActivityChart extends ChartWidget
{
    public const DAYS = 30;

    public const CACHE_SECONDS = 600;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Activity, last 30 days';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    public ?string $filter = 'views';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'views' => 'Job views',
            'applications' => 'Applications',
            'postings' => 'Postings published',
        ];
    }

    /**
     * The chart's own numbers in words. Filament builds the canvas's
     * accessible label from the heading and this description, so a
     * screen reader hears the gist of what is drawn.
     */
    public function getDescription(): string|Htmlable|null
    {
        $series = $this->series();
        $total = array_sum($series);
        $summary = $this->getFilters()[$this->metric()].': '.number_format($total).' in the last '.self::DAYS.' days';

        if ($total > 0) {
            $busiest = array_search(max($series), $series, true);
            $summary .= ', most on '.CarbonImmutable::parse($busiest)->format('j M').' ('.number_format($series[$busiest]).')';
        }

        return $summary.'.'.($this->metric() === 'views' ? ' Bots, staff and each company\'s own team are not counted.' : '');
    }

    protected function getData(): array
    {
        $series = $this->series();

        return [
            'datasets' => [[
                'label' => $this->getFilters()[$this->metric()],
                'data' => array_values($series),
            ]],
            'labels' => array_map(fn (string $date) => CarbonImmutable::parse($date)->format('j M'), array_keys($series)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    /**
     * Every day of the range, oldest first, with a count for each; a day
     * with nothing is 0, not missing.
     *
     * @return array<string, int>
     */
    private function series(): array
    {
        $metric = $this->metric();
        $from = CarbonImmutable::today()->subDays(self::DAYS - 1);

        $counted = Cache::remember(
            "admin-activity:{$metric}:{$from->toDateString()}",
            self::CACHE_SECONDS,
            fn () => $this->countsFrom($metric, $from),
        );

        $series = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            $date = $from->addDays($day)->toDateString();
            $series[$date] = (int) ($counted[$date] ?? 0);
        }

        return $series;
    }

    /**
     * @return array<string, int>
     */
    private function countsFrom(string $metric, CarbonImmutable $from): array
    {
        $query = match ($metric) {
            'views' => JobPostingDailyStat::query()
                ->where('date', '>=', $from->toDateString())
                ->groupBy('date')
                ->select('date as day', DB::raw('sum(views) as total')),
            'applications' => Application::query()
                ->where('created_at', '>=', $from)
                ->groupBy(DB::raw('date(created_at)'))
                ->select(DB::raw('date(created_at) as day'), DB::raw('count(*) as total')),
            'postings' => JobPosting::query()
                ->where('published_at', '>=', $from)
                ->groupBy(DB::raw('date(published_at)'))
                ->select(DB::raw('date(published_at) as day'), DB::raw('count(*) as total')),
        };

        return $query->toBase()->get()
            ->mapWithKeys(fn (object $row) => [CarbonImmutable::parse($row->day)->toDateString() => (int) $row->total])
            ->all();
    }

    /**
     * The chosen filter, or views when the value sent is not one of them.
     */
    private function metric(): string
    {
        return array_key_exists((string) $this->filter, $this->getFilters()) ? $this->filter : 'views';
    }
}
