<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Services\JobPerformance;
use App\Support\JobPerformanceReport;
use App\Support\JobPostingChecks;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Analytics')] class extends Component {
    public Company $company;

    /**
     * The posting's slug, or empty for every posting together. Kept in
     * the address so a view of one job can be bookmarked and shared with
     * the rest of the team.
     */
    #[Url]
    public string $job = '';

    #[Url]
    public int $range = JobPerformance::DEFAULT_RANGE;

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->range = $this->validRange($this->range);

        // Another company's slug, or one that does not exist, is a page
        // that does not exist here.
        if ($this->job !== '') {
            $this->selectedJob;
        }
    }

    public function updatedRange(): void
    {
        $this->range = $this->validRange($this->range);
    }

    #[Computed]
    public function jobPostings()
    {
        return $this->company->jobPostings()->latest()->latest('id')->get(['id', 'slug', 'title']);
    }

    #[Computed]
    public function selectedJob(): ?JobPosting
    {
        if ($this->job === '') {
            return null;
        }

        return $this->company->jobPostings()
            ->where('slug', $this->job)
            ->with('skills:id')
            ->firstOrFail();
    }

    #[Computed]
    public function report(): JobPerformanceReport
    {
        return app(JobPerformance::class)->for($this->company, $this->selectedJob, $this->range);
    }

    #[Computed]
    public function checks(): array
    {
        return $this->selectedJob !== null ? JobPostingChecks::for($this->selectedJob, $this->report) : [];
    }

    private function validRange(int $range): int
    {
        return in_array($range, JobPerformance::RANGES, true) ? $range : JobPerformance::DEFAULT_RANGE;
    }
}; ?>

@php
    $report = $this->report;
    $selected = $this->selectedJob;

    $duration = function (float $hours): string {
        $inDays = $hours >= 48;
        $count = rtrim(rtrim(number_format($inDays ? $hours / 24 : $hours, 1), '0'), '.');

        return match (true) {
            $inDays && $count === '1' => __('1 day'),
            $inDays => __(':count days', ['count' => $count]),
            $count === '1' => __('1 hour'),
            default => __(':count hours', ['count' => $count]),
        };
    };

    $funnelSteps = [
        ['label' => __('Applied'), 'count' => $report->applications],
        ['label' => __('Shortlisted'), 'count' => $report->funnel['shortlisted']],
        ['label' => __('Interview'), 'count' => $report->funnel['interview']],
        ['label' => __('Offer'), 'count' => $report->funnel['offer']],
        ['label' => __('Hired'), 'count' => $report->funnel['hired']],
    ];
@endphp

<div class="mx-auto flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">{{ __('Analytics') }}</flux:heading>
            <flux:text class="mt-1">
                {{ $selected ? $selected->title : __('All job postings together') }}
            </flux:text>
        </div>

        @if ($this->jobPostings->isNotEmpty())
            <div class="flex flex-wrap items-end gap-3">
                <flux:select wire:model.live="job" :label="__('Job posting')" class="w-64">
                    <flux:select.option value="">{{ __('All job postings') }}</flux:select.option>
                    @foreach ($this->jobPostings as $posting)
                        <flux:select.option value="{{ $posting->slug }}">{{ $posting->title }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="range" :label="__('Period')" class="w-40">
                    @foreach (\App\Services\JobPerformance::RANGES as $days)
                        <flux:select.option value="{{ $days }}">{{ __('Last :days days', ['days' => $days]) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->jobPostings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Nothing to measure yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Once you post a job, how it is doing shows up here.') }}</flux:text>
            @can('create', [\App\Models\JobPosting::class, $company])
                <flux:button class="mt-4" variant="primary" :href="route('employer.jobs.create', $company)" wire:navigate>{{ __('Post a job') }}</flux:button>
            @endcan
        </div>
    @else
        <div wire:loading.class="opacity-60" wire:target="job, range" class="flex flex-col gap-6 transition-opacity">
            @if ($selected && $report->job)
                @php $state = $report->job; @endphp
                <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                    @switch ($state['status'])
                        @case('draft')
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Not published yet.') }}</span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Views and applications start once it is live.') }}</span>
                            @break
                        @case('in_review')
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Waiting for review.') }}</span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('It goes live once it has been checked.') }}</span>
                            @break
                        @case('rejected')
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Not approved in review.') }}</span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('It is not public. The job postings page says why.') }}</span>
                            @break
                        @case('live')
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ trans_choice('{0} Live since today.|{1} Live for :count day.|[2,*] Live for :count days.', $state['live_days'] ?? 0, ['count' => $state['live_days'] ?? 0]) }}</span>
                            <span class="text-zinc-600 dark:text-zinc-400">{{ trans_choice('{0} Closes today.|{1} Closes in :count day.|[2,*] Closes in :count days.', $state['expires_in_days'] ?? 0, ['count' => $state['expires_in_days'] ?? 0]) }}</span>
                            @break
                        @default
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('No longer taking applications.') }}</span>
                            @if ($state['live_days'] !== null)
                                <span class="text-zinc-600 dark:text-zinc-400">{{ trans_choice('{0} It was live for less than a day.|{1} It was live for :count day.|[2,*] It was live for :count days.', $state['live_days'], ['count' => $state['live_days']]) }}</span>
                            @endif
                    @endswitch
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Views') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($report->views) }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Applications') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($report->applications) }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Apply rate') }}</div>
                    @if ($report->applyRate !== null)
                        <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ rtrim(rtrim(number_format($report->applyRate, 1), '0'), '.') }}%</div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Applications per hundred views') }}</div>
                    @else
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Not enough data yet: it needs :count views.', ['count' => \App\Services\JobPerformance::MIN_VIEWS_FOR_RATE]) }}</div>
                    @endif
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Saved by candidates') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($report->saves) }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('All time') }}</div>
                </div>
            </div>

            @if (! $report->viewsCoverRange())
                <flux:text size="sm">
                    {{ $report->viewsCountedSince
                        ? __('Views have been counted since :date, so earlier days show none.', ['date' => $report->viewsCountedSince->format(\App\Support\DateFormat::DAY)])
                        : __('No views counted yet.') }}
                </flux:text>
            @endif

            <flux:text size="sm">
                {{ __('Days run from midnight to midnight in your company\'s time zone, :zone.', ['zone' => \App\Support\LocalTime::label($company->timezone)]) }}
                @can('update', $company)
                    <flux:link :href="route('employer.company.edit', $company)">{{ __('Change it') }}</flux:link>
                @endcan
            </flux:text>

            <div class="grid gap-4 lg:grid-cols-2" wire:key="trends-{{ $job }}-{{ $range }}">
                <x-trend-chart :title="__('Views per day')" :series="$report->dailyViews" :value-label="__('Views')" />
                <x-trend-chart :title="__('Applications per day')" :series="$report->dailyApplications" :value-label="__('Applications')" type="bar" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="funnel-heading">
                    <h2 id="funnel-heading" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('How far applicants got') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Applications from this period, by the furthest step each reached so far.') }}</p>

                    @if ($report->applications === 0)
                        <flux:text class="mt-4">{{ __('No applications in this period.') }}</flux:text>
                    @else
                        <dl class="mt-4 flex flex-col gap-3">
                            @foreach ($funnelSteps as $step)
                                <div>
                                    <div class="flex justify-between text-sm">
                                        <dt class="text-zinc-700 dark:text-zinc-300">{{ $step['label'] }}</dt>
                                        <dd class="tabular-nums text-zinc-900 dark:text-zinc-100">{{ $step['count'] }}</dd>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-brand-50 dark:bg-brand-900" aria-hidden="true">
                                        <div class="h-2 rounded-full bg-brand-600 dark:bg-brand-500" style="width: {{ $report->applications > 0 ? round($step['count'] / $report->applications * 100, 1) : 0 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                        @if ($report->rejectedUnseen > 0)
                            <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ trans_choice('{1} :count application was rejected without being moved from New.|[2,*] :count applications were rejected without being moved from New.', $report->rejectedUnseen, ['count' => $report->rejectedUnseen]) }}
                            </p>
                        @endif
                    @endif
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="match-heading">
                    <h2 id="match-heading" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('How well applicants match') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('The skill match you see beside each application, for this period.') }}</p>

                    @if ($report->skillMatch === null)
                        <flux:text class="mt-4">{{ __('Nothing to show: there are no applications with a match score in this period. A score needs skills on the posting and on the applicant\'s profile.') }}</flux:text>
                    @else
                        @php $scored = array_sum($report->skillMatch); @endphp
                        <dl class="mt-4 flex flex-col gap-3">
                            @foreach (['high' => __('Strong match (70% and up)'), 'medium' => __('Partial match (40–69%)'), 'low' => __('Weak match (under 40%)')] as $bucket => $label)
                                <div>
                                    <div class="flex justify-between text-sm">
                                        <dt class="text-zinc-700 dark:text-zinc-300">{{ $label }}</dt>
                                        <dd class="tabular-nums text-zinc-900 dark:text-zinc-100">{{ $report->skillMatch[$bucket] }}</dd>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-brand-50 dark:bg-brand-900" aria-hidden="true">
                                        <div class="h-2 rounded-full bg-brand-600 dark:bg-brand-500" style="width: {{ round($report->skillMatch[$bucket] / $scored * 100, 1) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                        @if ($report->applications > $scored)
                            <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ trans_choice('{1} 1 application has no score, because the posting or the applicant lists no skills.|[2,*] :count applications have no score, because the posting or the applicant lists no skills.', $report->applications - $scored, ['count' => $report->applications - $scored]) }}
                            </p>
                        @endif
                    @endif
                </section>
            </div>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="response-heading">
                <h2 id="response-heading" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('How quickly you answer') }}</h2>

                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('First response, typical') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">
                            @if ($report->firstResponseMedianHours !== null)
                                <span class="text-xl font-semibold tabular-nums">{{ $duration($report->firstResponseMedianHours) }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Median, over :count answered applications from this period', ['count' => $report->responded]) }}</span>
                            @else
                                <span class="text-zinc-600 dark:text-zinc-400">{{ __('Not enough data yet: it needs :count answered applications.', ['count' => \App\Services\JobPerformance::MIN_VALUES_FOR_MEDIAN]) }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Waiting on you now') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">
                            <span class="text-xl font-semibold tabular-nums">{{ $report->waiting }}</span>
                            @if ($report->oldestWaitingSince)
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('The oldest arrived :when', ['when' => $report->oldestWaitingSince->diffForHumans()]) }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Time to hire, typical') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">
                            @if ($report->timeToHireMedianDays !== null)
                                <span class="text-xl font-semibold tabular-nums">{{ $duration($report->timeToHireMedianDays * 24) }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('From application to hire, over :count hires in this period', ['count' => $report->hires]) }}</span>
                            @else
                                <span class="text-zinc-600 dark:text-zinc-400">{{ trans_choice('{0} No hires in this period.|{1} One hire in this period; a typical time needs :min.|[2,*] :count hires in this period; a typical time needs :min.', $report->hires, ['count' => $report->hires, 'min' => \App\Services\JobPerformance::MIN_VALUES_FOR_MEDIAN]) }}</span>
                            @endif
                            @if ($report->hires > 0)
                                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Counted on the day of the hire, so it can include someone who applied before this period.') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($selected && isset($report->timeToFillDays[$selected->id]))
                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ trans_choice('{0} Filled the day it was published.|{1} Filled :count day after it was published.|[2,*] Filled :count days after it was published.', $report->timeToFillDays[$selected->id], ['count' => $report->timeToFillDays[$selected->id]]) }}
                    </p>
                @endif
            </section>

            @if ($selected)
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="checks-heading">
                    <h2 id="checks-heading" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('What could help this posting') }}</h2>

                    @if ($this->checks === [])
                        <flux:text class="mt-2">{{ __('Nothing stands out. The posting covers what candidates look for.') }}</flux:text>
                    @else
                        <ul class="mt-3 flex flex-col gap-2 text-sm">
                            @foreach ($this->checks as $gap)
                                <li class="flex gap-2 text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.light-bulb variant="mini" class="mt-0.5 shrink-0 text-warning-600 dark:text-warning-400" />
                                    <span>{{ $gap->message() }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @can('update', $selected)
                            <flux:button class="mt-4" size="sm" :href="route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $selected])" wire:navigate>
                                {{ __('Edit the posting') }}
                            </flux:button>
                        @endcan
                    @endif
                </section>

                <livewire:job-post-review :job-posting="$selected" :key="'ai-review-'.$selected->id" />
            @endif
        </div>
    @endif
</div>
