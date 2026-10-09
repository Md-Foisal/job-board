<?php

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\JobApplicants;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One job's applicants, as a list or as a board with a column per stage
 * -- Greenhouse, Lever and Workable offer both. The list filters by
 * stage with a tab per stage; the board shows every open application at
 * once. A card moves with the same "Move to" menu as the applicant page:
 * no dragging in this version, so a keyboard and a phone work the same
 * way as a mouse.
 */
new #[Layout('layouts::employer')] #[Title('Applications')] class extends Component {
    public Company $company;

    public JobPosting $jobPosting;

    /**
     * In the address, so the dashboard's count for one stage opens the
     * list at that stage.
     */
    #[Url(as: 'stage', except: 'all')]
    public string $stageFilter = 'all';

    #[Url(except: 'match')]
    public string $sort = 'match';

    #[Url(except: 'list')]
    public string $view = 'list';

    /** @var array<int, int> */
    public array $selected = [];

    public function mount(Company $company, JobPosting $jobPosting): void
    {
        $this->authorize('reviewAny', [Application::class, $jobPosting]);

        $this->company = $company;
        $this->jobPosting = $jobPosting;

        $this->normalise();
    }

    public function updated(string $property): void
    {
        $this->normalise();

        // A selection made in one view or filter would act on rows the
        // person can no longer see.
        if (in_array($property, ['view', 'sort', 'stageFilter'], true)) {
            $this->reset('selected');
        }
    }

    #[Computed]
    public function applications()
    {
        return app(JobApplicants::class)->list(
            $this->jobPosting,
            $this->view === 'board' ? 'all' : $this->stageFilter,
            $this->sort,
        );
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return app(JobApplicants::class)->counts($this->jobPosting);
    }

    /**
     * The board's columns: the open applications at each stage, in the
     * order the sort asks for. Decided ones stay on the list, under All.
     */
    #[Computed]
    public function columns()
    {
        $open = $this->applications->where('outcome_status', ApplicationOutcomeStatus::Active);

        return collect(ApplicationStage::cases())->mapWithKeys(fn (ApplicationStage $stage) => [
            $stage->value => $open->where('stage', $stage)->values(),
        ]);
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

        // Closed applications are left where they are, as on the single
        // application page: their candidate already has an answer.
        $open = $this->jobPosting->applications()
            ->whereIn('id', $this->selected)
            ->where('outcome_status', ApplicationOutcomeStatus::Active)
            ->get();

        $open->each(fn (Application $application) => $changeStage($application, auth()->user(), $to));

        $moved = $open->count();
        $this->reset('selected');
        unset($this->applications, $this->counts, $this->columns);

        Flux::toast(variant: 'success', text: trans_choice(
            '{0} None of those applications is still open, so nothing moved|{1} One application moved to :stage|[2,*] :count applications moved to :stage',
            $moved,
            ['stage' => $to->label()],
        ));
    }

    /**
     * One card on the board, moved by anyone on the team -- the same
     * right as on the applicant page, where a member moves one
     * application at a time.
     */
    public function move(ChangeApplicationStage $changeStage, int $applicationId, string $stage): void
    {
        $application = $this->jobPosting->applications()->findOrFail($applicationId);

        $this->authorize('updateStage', $application);

        $to = ApplicationStage::tryFrom($stage);

        abort_if($to === null, 422);

        $changeStage($application, auth()->user(), $to);

        unset($this->applications, $this->counts, $this->columns);

        Flux::toast(variant: 'success', text: __(':name moved to :stage.', [
            'name' => $application->candidateProfile->user->name,
            'stage' => $to->label(),
        ]));
    }

    /**
     * Values from the address or the browser are checked, so a hand-edited
     * one falls back to the default instead of failing.
     */
    private function normalise(): void
    {
        if (! JobApplicants::isStage($this->stageFilter)) {
            $this->stageFilter = 'all';
        }

        if (! in_array($this->sort, JobApplicants::SORTS, true)) {
            $this->sort = 'match';
        }

        if (! in_array($this->view, ['list', 'board'], true)) {
            $this->view = 'list';
        }
    }
}; ?>

<x-page>
    @php
        $canBulk = auth()->user()->can('bulkUpdateStage', [Application::class, $this->jobPosting]);
        // Everyone who can open this page may move one open application
        // (ApplicationPolicy::updateStage); the board holds only open ones,
        // so this is asked once rather than once per card.
        $canMove = auth()->user()->can('reviewAny', [Application::class, $this->jobPosting]);
        $scored = $this->jobPosting->skills->isNotEmpty();

        // Every link out of this page keeps the list as it is now, so the
        // applicant page's back link and previous/next return to it.
        $keep = array_filter([
            'stage' => $view === 'list' && $stageFilter !== 'all' ? $stageFilter : null,
            'sort' => $sort !== 'match' ? $sort : null,
        ]);
        $applicantUrl = fn (Application $application) => route('employer.applications.show', [
            'company' => $this->company,
            'application' => $application,
            ...$keep,
        ]);
        $tabUrl = fn (string $stage) => route('employer.jobs.applications', array_filter([
            'company' => $this->company,
            'jobPosting' => $this->jobPosting,
            'stage' => $stage !== 'all' ? $stage : null,
            'sort' => $sort !== 'match' ? $sort : null,
        ]));
    @endphp

    <x-page-header :title="__('Applications')" :back="route('employer.jobs.index', $this->company)" :back-label="__('Job postings')">
        {{ $this->jobPosting->title }}
        &middot;
        {{ trans_choice('{0} No applicants yet|{1} :count applicant|[2,*] :count applicants', $this->counts['all'], ['count' => $this->counts['all']]) }}

        <x-slot:actions>
            <flux:button size="sm" variant="ghost" icon="eye" :href="route('jobs.show', $this->jobPosting)">{{ __('View job page') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($view === 'list')
        <x-tab-nav :label="__('Stage')">
            <x-tab-nav.item :href="$tabUrl('all')" :current="$stageFilter === 'all'">
                {{ __('All') }} <span class="ms-1 tabular-nums text-ink-muted">{{ $this->counts['all'] }}</span>
            </x-tab-nav.item>
            @foreach (ApplicationStage::cases() as $stage)
                <x-tab-nav.item :href="$tabUrl($stage->value)" :current="$stageFilter === $stage->value">
                    {{ __($stage->label()) }} <span class="ms-1 tabular-nums text-ink-muted">{{ $this->counts[$stage->value] }}</span>
                </x-tab-nav.item>
            @endforeach
        </x-tab-nav>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <flux:radio.group wire:model.live="view" variant="segmented" size="sm" :aria-label="__('Show as')">
                <flux:radio value="list" icon="list-bullet" :label="__('List')" />
                <flux:radio value="board" icon="view-columns" :label="__('Board')" />
            </flux:radio.group>

            <flux:select wire:model.live="sort" size="sm" class="w-44" :aria-label="__('Sort by')">
                <flux:select.option value="match">{{ __('Best match') }}</flux:select.option>
                <flux:select.option value="experience">{{ __('Most experience') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest first') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest first') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($canBulk && $view === 'list' && count($selected) > 0)
            <div class="flex items-center gap-2">
                <flux:text size="sm">{{ trans_choice('{1} :count selected|[2,*] :count selected', count($selected), ['count' => count($selected)]) }}</flux:text>

                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" variant="primary" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="moveSelected">
                        <span wire:loading.remove wire:target="moveSelected">{{ __('Move to') }}</span>
                        <span wire:loading wire:target="moveSelected">{{ __('Moving...') }}</span>
                    </flux:button>

                    <flux:menu>
                        @foreach (ApplicationStage::cases() as $stage)
                            <flux:menu.item wire:click="moveSelected('{{ $stage->value }}')">{{ __($stage->label()) }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endif
    </div>

    {{-- While a sort, a view switch or a move is on its way, the rows
         fade instead of standing still and then jumping. --}}
    <div class="transition-opacity" wire:loading.delay.class="opacity-60" wire:target="sort,view,moveSelected,move">
        @if ($this->counts['all'] === 0)
            <x-empty-state icon="inbox" :heading="__('No applications yet')" :action-href="route('jobs.show', $this->jobPosting)" :action-label="__('View job page')">
                {{ __('Applicants appear here the moment they apply, best match first.') }}
            </x-empty-state>
        @elseif ($view === 'board')
            {{-- One column per stage. On a phone the columns scroll sideways,
                 each a screen wide, as boards do on small screens. --}}
            <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 lg:grid lg:grid-cols-4 lg:overflow-visible">
                @foreach (ApplicationStage::cases() as $stage)
                    @php $cards = $this->columns[$stage->value]; @endphp

                    <section class="flex w-[85%] shrink-0 snap-start flex-col gap-3 rounded-card bg-surface p-3 ring-1 ring-line sm:w-72 lg:w-auto" aria-labelledby="column-{{ $stage->value }}">
                        <h2 id="column-{{ $stage->value }}" class="flex items-center justify-between px-1 text-sm font-semibold text-ink">
                            {{ __($stage->label()) }}
                            <span class="rounded-full bg-canvas px-2 py-0.5 text-xs tabular-nums text-ink-muted ring-1 ring-line">{{ $cards->count() }}</span>
                        </h2>

                        @forelse ($cards as $application)
                            @php $person = $application->candidateProfile->user; @endphp

                            <article wire:key="card-{{ $application->id }}" class="relative rounded-control bg-canvas p-3 shadow-xs ring-1 ring-line transition hover:ring-line-strong">
                                <div class="flex items-start gap-3">
                                    <flux:avatar
                                        circle
                                        size="sm"
                                        :src="$person->avatar ? \Illuminate\Support\Facades\Storage::url($person->avatar) : null"
                                        :name="$person->name"
                                        :initials="$person->initials()"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $applicantUrl($application) }}" wire:navigate class="block truncate text-sm font-medium text-ink after:absolute after:inset-0 hover:text-sunset-small">
                                            {{ $person->name }}
                                        </a>
                                        @if ($application->candidateProfile->headline)
                                            <p class="truncate text-xs text-ink-muted">{{ $application->candidateProfile->headline }}</p>
                                        @endif
                                    </div>

                                    @if ($canMove)
                                        <flux:dropdown position="bottom" align="end" class="relative z-10">
                                            <flux:button size="xs" variant="ghost" icon="arrows-right-left" :aria-label="__('Move :name', ['name' => $person->name])" />

                                            <flux:menu>
                                                <flux:menu.heading>{{ __('Move to') }}</flux:menu.heading>
                                                @foreach (ApplicationStage::cases() as $to)
                                                    @if ($to !== $stage)
                                                        <flux:menu.item wire:click="move({{ $application->id }}, '{{ $to->value }}')">{{ __($to->label()) }}</flux:menu.item>
                                                    @endif
                                                @endforeach
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-2 text-xs text-ink-muted">
                                    <time datetime="{{ $application->created_at->toIso8601String() }}" title="{{ \App\Support\LocalTime::of($application->created_at)->format(\App\Support\DateFormat::MOMENT) }}">
                                        {{ $application->created_at->diffForHumans() }}
                                    </time>
                                    <x-match-score :score="$application->match_score" />
                                </div>
                            </article>
                        @empty
                            <p class="rounded-control px-3 py-6 text-center text-xs text-ink-muted">{{ __('No one at this stage') }}</p>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @elseif ($this->applications->isEmpty())
            <x-empty-state icon="inbox" :heading="__('No one at :stage right now', ['stage' => __(ApplicationStage::from($stageFilter)->label())])" :action-href="$tabUrl('all')" :action-label="__('See all applicants')">
                {{ __('Applications move here when someone on your team moves them to this stage.') }}
            </x-empty-state>
        @else
            <x-card padding="none" class="overflow-hidden">
                @if ($canBulk)
                    <div class="flex items-center gap-4 border-b border-line bg-surface px-5 py-3">
                        <flux:checkbox
                            wire:click="toggleAll"
                            :checked="count($selected) === $this->applications->count()"
                            :indeterminate="count($selected) > 0 && count($selected) < $this->applications->count()"
                            :aria-label="__('Select all applicants')"
                        />
                        <flux:text size="sm">{{ __('Select all') }}</flux:text>
                    </div>
                @endif

                <ul class="divide-y divide-line">
                    @foreach ($this->applications as $application)
                        @php
                            $profile = $application->candidateProfile;
                            $person = $profile->user;
                        @endphp

                        <li wire:key="application-{{ $application->id }}" class="relative flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition hover:bg-surface">
                            @if ($canBulk)
                                <flux:checkbox
                                    wire:model.live="selected"
                                    value="{{ $application->id }}"
                                    class="relative z-10"
                                    :aria-label="__('Select :name', ['name' => $person->name])"
                                />
                            @endif

                            <flux:avatar
                                circle
                                :src="$person->avatar ? \Illuminate\Support\Facades\Storage::url($person->avatar) : null"
                                :name="$person->name"
                                :initials="$person->initials()"
                            />

                            <div class="min-w-0 flex-1">
                                <a href="{{ $applicantUrl($application) }}" wire:navigate class="font-medium text-ink after:absolute after:inset-0 hover:text-sunset-small">
                                    {{ $person->name }}
                                </a>
                                @if ($profile->headline)
                                    <p class="truncate text-sm text-ink-soft">{{ $profile->headline }}</p>
                                @endif
                                <p class="text-xs text-ink-muted">
                                    <time datetime="{{ $application->created_at->toIso8601String() }}" title="{{ \App\Support\LocalTime::of($application->created_at)->format(\App\Support\DateFormat::MOMENT) }}">
                                        {{ __('Applied :date', ['date' => $application->created_at->diffForHumans()]) }}
                                    </time>
                                </p>
                            </div>

                            {{-- Beside the name from sm up; under it on a phone, lined up
                             with the name, so the name keeps its width. --}}
                        <div @class([
                            'flex shrink-0 items-center gap-3 max-sm:basis-full',
                            'max-sm:ps-[5.75rem]' => $canBulk,
                            'max-sm:ps-14' => ! $canBulk,
                        ])>
                                @if ($application->match_score !== null)
                                    <x-match-score :score="$application->match_score" />
                                @elseif ($scored)
                                    {{-- No score is not a low score: there was nothing
                                         to compare. Saying why stops a blank reading
                                         as a verdict. --}}
                                    <span class="text-xs text-ink-muted">{{ __('No skills listed') }}</span>
                                @endif

                                <x-application-status :application="$application" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-page>
