<?php

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\MatchScoreCalculator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Applications')] class extends Component {
    public Company $company;

    public JobPosting $jobPosting;

    public string $stageFilter = 'all';

    public string $sort = 'match';

    /** @var array<int, int> */
    public array $selected = [];

    public function mount(Company $company, JobPosting $jobPosting): void
    {
        $this->authorize('reviewAny', [Application::class, $jobPosting]);

        $this->company = $company;
        $this->jobPosting = $jobPosting;
    }

    #[Computed]
    public function applications()
    {
        $applications = $this->jobPosting->applications()
            ->with([
                'candidateProfile.user:id,name,email,avatar',
                // Needed for the match percentage, and easy to forget: without
                // it this page would run one query per applicant to answer the
                // question it exists to answer.
                'candidateProfile.skills:id',
                'resumeDocument',
            ])
            ->when($this->stageFilter !== 'all', fn ($query) => $query->where('stage', $this->stageFilter))
            ->get();

        $calculator = app(MatchScoreCalculator::class);

        $applications->each(function (Application $application) use ($calculator) {
            $application->match_score = $calculator->calculate(
                $this->jobPosting,
                $application->candidateProfile->skills->pluck('id'),
            );
        });

        return match ($this->sort) {
            'newest' => $applications->sortByDesc('created_at')->values(),
            'oldest' => $applications->sortBy('created_at')->values(),
            default => $applications->sortByDesc(fn ($a) => $a->match_score ?? -1)->values(),
        };
    }

    /**
     * Ticking each of forty applicants by hand is not a workflow, and the
     * bulk actions above are useless without it. Driven from the server
     * rather than from Alpine scanning the DOM, so it selects exactly what
     * the current stage filter is showing -- which is what someone who
     * filtered to "New" and pressed select-all means.
     */
    public function toggleAll(): void
    {
        $visible = $this->applications->pluck('id')->all();

        $this->selected = count($this->selected) === count($visible) && $visible !== []
            ? []
            : $visible;
    }

    public function moveSelected(ChangeApplicationStage $changeStage, string $stage): void
    {
        $this->authorize('bulkUpdateStage', [Application::class, $this->jobPosting]);

        $to = ApplicationStage::from($stage);

        $this->jobPosting->applications()
            ->whereIn('id', $this->selected)
            ->get()
            ->each(fn (Application $application) => $changeStage($application, auth()->user(), $to));

        $moved = count($this->selected);
        $this->reset('selected');
        unset($this->applications);

        Flux::toast(variant: 'success', text: trans_choice(
            '{1} One application moved to :stage|[2,*] :count applications moved to :stage',
            $moved,
            ['stage' => $to->label()],
        ));
    }
}; ?>

<div class="mx-auto flex max-w-5xl flex-col gap-6">
    <div>
        <x-breadcrumb :items="[
            ['label' => __('Job postings'), 'url' => route('employer.jobs.index', $this->company)],
            ['label' => $this->jobPosting->title],
        ]" />

        <flux:heading size="xl" class="mt-4 font-display">{{ __('Applications') }}</flux:heading>
        <flux:text class="mt-1">
            {{ $this->jobPosting->title }}
            &middot;
            {{ trans_choice('{0} No applicants yet|{1} :count applicant|[2,*] :count applicants', $this->applications->count(), ['count' => $this->applications->count()]) }}
        </flux:text>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap gap-4">
            <flux:select wire:model.live="stageFilter" :label="__('Stage')" size="sm" class="w-44">
                <flux:select.option value="all">{{ __('All stages') }}</flux:select.option>
                @foreach (ApplicationStage::cases() as $stage)
                    <flux:select.option value="{{ $stage->value }}">{{ $stage->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="sort" :label="__('Sort by')" size="sm" class="w-44">
                <flux:select.option value="match">{{ __('Best match') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest first') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest first') }}</flux:select.option>
            </flux:select>
        </div>

        @can('bulkUpdateStage', [\App\Models\Application::class, $this->jobPosting])
            @if (count($selected) > 0)
                <div class="flex items-center gap-2">
                    <flux:text size="sm">{{ trans_choice('{1} :count selected|[2,*] :count selected', count($selected), ['count' => count($selected)]) }}</flux:text>

                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="primary" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="moveSelected">
                            <span wire:loading.remove wire:target="moveSelected">{{ __('Move to') }}</span>
                            <span wire:loading wire:target="moveSelected">{{ __('Moving...') }}</span>
                        </flux:button>

                        <flux:menu>
                            @foreach (ApplicationStage::cases() as $stage)
                                <flux:menu.item wire:click="moveSelected('{{ $stage->value }}')">{{ $stage->label() }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @endif
        @endcan
    </div>

    @if ($this->applications->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __('No applications here yet.') }}</flux:text>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            @can('bulkUpdateStage', [\App\Models\Application::class, $this->jobPosting])
                <div class="flex items-center gap-4 border-b border-zinc-200 bg-zinc-50 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                    <flux:checkbox
                        wire:click="toggleAll"
                        :checked="count($selected) === $this->applications->count()"
                        :indeterminate="count($selected) > 0 && count($selected) < $this->applications->count()"
                        :aria-label="__('Select all applicants')"
                    />
                    <flux:text size="sm">{{ __('Select all') }}</flux:text>
                </div>
            @endcan

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($this->applications as $application)
                    <li wire:key="application-{{ $application->id }}" class="flex flex-wrap items-center gap-4 px-5 py-4">
                        @can('bulkUpdateStage', [\App\Models\Application::class, $this->jobPosting])
                            <flux:checkbox
                                wire:model.live="selected"
                                value="{{ $application->id }}"
                                :aria-label="__('Select :name', ['name' => $application->candidateProfile->user->name])"
                            />
                        @endcan

                        <div class="min-w-0 flex-1">
                            <a
                                href="{{ route('employer.applications.show', ['company' => $this->company, 'application' => $application]) }}"
                                class="font-medium text-zinc-900 hover:text-brand-700 dark:text-zinc-100 dark:hover:text-brand-400"
                                wire:navigate
                            >
                                {{ $application->candidateProfile->user->name }}
                            </a>
                            <div class="text-sm text-zinc-500 dark:text-zinc-500">
                                {{ __('Applied :date', ['date' => $application->created_at->diffForHumans()]) }}
                            </div>
                        </div>

                        <x-match-score :score="$application->match_score" />

                        <x-application-status :application="$application" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
