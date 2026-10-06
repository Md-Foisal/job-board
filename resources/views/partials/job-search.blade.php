{{--
    The listing the job search and every category page share (their
    component state lives in FiltersJobPostings): what and where at the
    top, the filters beside the results on a wide screen, and the filters
    as they stand in a row of chips over the results.

    On a phone the same filter panel opens as a full-screen sheet behind a
    "Filters" button, so the first job is on the first screen instead of
    below eight fields. One panel for both, not two copies: it is the
    responsive classes that move it, keyed on data-open. The results update
    behind the sheet as choices are made, and its button says how many
    there will be before it is pressed.
--}}
<div
    x-data="{ filtersOpen: false }"
    x-init="window.matchMedia('(min-width: 64rem)').addEventListener('change', (event) => { if (event.matches) filtersOpen = false })"
    x-on:keydown.escape.window="filtersOpen = false"
>
    <div role="search" class="flex flex-col gap-1.5 rounded-2xl border border-line bg-canvas p-1.5 shadow-lift transition-shadow focus-within:border-line-strong sm:flex-row">
        <label class="flex min-h-11 flex-1 items-center gap-2.5 rounded-xl px-3.5 focus-within:bg-surface">
            <flux:icon.magnifying-glass class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
            <span class="sr-only">{{ __('Job title, skill or company') }}</span>
            <input
                type="search"
                wire:model.live.debounce.400ms="q"
                placeholder="{{ __('Job title, skill or company') }}"
                autocomplete="off"
                class="w-full min-w-0 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:outline-none"
            >
        </label>

        <label class="flex min-h-11 items-center gap-2.5 rounded-xl border-t border-line px-3.5 focus-within:bg-surface sm:w-60 sm:border-t-0 sm:border-l">
            <flux:icon.map-pin class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
            <span class="sr-only">{{ __('City, or remote') }}</span>
            <input
                type="text"
                wire:model.live.debounce.400ms="location"
                placeholder="{{ __('City or remote') }}"
                autocomplete="off"
                class="w-full min-w-0 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:outline-none"
            >
        </label>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside
            id="job-filters"
            aria-labelledby="job-filters-heading"
            x-bind:data-open="filtersOpen"
            x-bind:role="filtersOpen ? 'dialog' : null"
            x-bind:aria-modal="filtersOpen ? 'true' : null"
            x-trap.inert.noscroll="filtersOpen"
            class="bg-canvas max-lg:hidden max-lg:data-open:fixed max-lg:data-open:inset-0 max-lg:data-open:z-50 max-lg:data-open:flex max-lg:data-open:flex-col lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7rem)] lg:self-start lg:overflow-y-auto"
        >
            <div class="flex items-center justify-between gap-3 max-lg:border-b max-lg:border-line max-lg:px-4 max-lg:py-3">
                <h2 id="job-filters-heading" class="text-subheading text-ink">{{ __('Filters') }}</h2>

                @if ($activeFilterCount > 0)
                    <button type="button" wire:click="resetFilters" class="text-sm font-medium max-lg:hidden">
                        <span class="text-sunset-small hover:underline">{{ __('Clear all') }}</span>
                    </button>
                @endif

                <flux:button variant="ghost" size="sm" square icon="x-mark" x-on:click="filtersOpen = false" class="lg:hidden" :aria-label="__('Close filters')" />
            </div>

            <div class="space-y-5 max-lg:flex-1 max-lg:overflow-y-auto max-lg:px-4 max-lg:py-5 lg:mt-4 lg:pe-1">
                <flux:select wire:model.live="posted" :label="__('Date posted')">
                    <flux:select.option value="">{{ __('Any time') }}</flux:select.option>
                    @foreach ($postedOptions as $days => $label)
                        <flux:select.option value="{{ $days }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="workplaceType" :label="__('Workplace')">
                    <flux:select.option value="">{{ __('Any workplace') }}</flux:select.option>
                    @foreach ($workplaceTypes as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="employmentType" :label="__('Job type')">
                    <flux:select.option value="">{{ __('Any job type') }}</flux:select.option>
                    @foreach ($employmentTypes as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($categories->isNotEmpty())
                    <flux:select wire:model.live="category" :label="__('Category')">
                        <flux:select.option value="">{{ __('Any category') }}</flux:select.option>
                        @foreach ($categories as $categoryOption)
                            <flux:select.option value="{{ $categoryOption->id }}">{{ $categoryOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model.live="skill" :label="__('Skill')">
                    <flux:select.option value="">{{ __('Any skill') }}</flux:select.option>
                    @foreach ($skills as $skillOption)
                        <flux:select.option value="{{ $skillOption->id }}">{{ $skillOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="space-y-2">
                    <flux:select wire:model.live="currency" :label="__('Pay')">
                        <flux:select.option value="">{{ __('Any currency') }}</flux:select.option>
                        @foreach ($payCurrencies as $code => $label)
                            <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    {{-- Amounts mean nothing until their currency is known, so
                         they are asked for only once one is chosen. --}}
                    @if ($payCurrency)
                        <div class="grid grid-cols-2 gap-2">
                            <flux:input wire:model.live.debounce.400ms="salaryMin" type="number" min="1" :placeholder="__('Min / month')" :aria-label="__('Minimum pay a month, in :currency', ['currency' => $payCurrency])" />
                            <flux:input wire:model.live.debounce.400ms="salaryMax" type="number" min="1" :placeholder="__('Max / month')" :aria-label="__('Maximum pay a month, in :currency', ['currency' => $payCurrency])" />
                        </div>
                    @endif
                </div>

                {{-- Postings that ask for no more than this, so it is the
                     visitor's own experience, not a minimum they demand. --}}
                <flux:input wire:model.live.debounce.400ms="experience" type="number" min="1" inputmode="numeric" :label="__('Your experience')" :placeholder="__('Years, e.g. 3')" />
            </div>

            <div class="flex items-center gap-3 border-t border-line px-4 py-3 lg:hidden">
                @if ($activeFilterCount > 0)
                    <flux:button variant="ghost" wire:click="resetFilters">{{ __('Clear all') }}</flux:button>
                @endif
                {{-- The one thing to do in the sheet, so it is the page's main
                     action and carries the Sunset fill. --}}
                <flux:button variant="primary" class="btn-sunset flex-1" x-on:click="filtersOpen = false" wire:loading.class="opacity-60">
                    {{ trans_choice('Show :count job|Show :count jobs', $jobPostings->total(), ['count' => number_format($jobPostings->total())]) }}
                </flux:button>
            </div>
        </aside>

        <div>
            <div class="flex items-center justify-between gap-3">
                <p class="whitespace-nowrap text-sm text-ink-muted" role="status">
                    {{ trans_choice(':count opening|:count openings', $jobPostings->total(), ['count' => number_format($jobPostings->total())]) }}
                </p>

                <flux:select wire:model.live="sort" size="sm" class="w-auto max-w-[60%]" :aria-label="__('Sort')">
                    <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                    @if ($canSortByMatch)
                        <flux:select.option value="match">{{ __('Best match') }}</flux:select.option>
                    @endif
                    @if ($payCurrency)
                        <flux:select.option value="salary_high">{{ __('Pay in :currency: high to low', ['currency' => $payCurrency]) }}</flux:select.option>
                        <flux:select.option value="salary_low">{{ __('Pay in :currency: low to high', ['currency' => $payCurrency]) }}</flux:select.option>
                    @endif
                </flux:select>
            </div>

            {{-- On a phone this row stays under the navbar while the
                 results scroll, so the filters are always one tap away; it
                 scrolls sideways rather than growing taller. z-15 keeps it
                 over the cards' own z-10 links and under the navbar. --}}
            <div class="mt-3 flex items-center gap-2 max-lg:sticky max-lg:top-14 max-lg:z-15 max-lg:-mx-4 max-lg:overflow-x-auto max-lg:bg-canvas/95 max-lg:px-4 max-lg:py-2 max-lg:backdrop-blur-md sm:max-lg:-mx-6 sm:max-lg:px-6 lg:flex-wrap">
                <flux:button
                    size="sm"
                    icon="adjustments-horizontal"
                    class="shrink-0 lg:hidden"
                    x-on:click="filtersOpen = true"
                    aria-controls="job-filters"
                    x-bind:aria-expanded="filtersOpen ? 'true' : 'false'"
                >
                    {{ __('Filters') }}
                    @if ($activeFilterCount > 0)
                        <span class="ms-0.5 rounded-full bg-ink px-1.5 text-xs font-semibold tabular-nums text-canvas">{{ $activeFilterCount }}</span>
                    @endif
                </flux:button>

                <button
                    type="button"
                    wire:click="toggleRemote"
                    aria-pressed="{{ $remoteOnly ? 'true' : 'false' }}"
                    class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-line px-3 text-meta font-medium text-ink-soft transition-colors hover:border-line-strong hover:text-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-canvas"
                >
                    <flux:icon.globe-alt variant="micro" class="size-3.5" aria-hidden="true" />
                    {{ __('Remote') }}
                </button>

                @foreach ($appliedFilters as $applied)
                    <x-chip wire:key="filter-{{ $applied['filter'] }}" class="h-8 shrink-0 pe-1">
                        {{ $applied['label'] }}
                        <button
                            type="button"
                            wire:click="clearFilter('{{ $applied['filter'] }}')"
                            class="ms-0.5 rounded-md p-0.5 text-ink-muted transition-colors hover:bg-line hover:text-ink"
                            aria-label="{{ __('Remove filter: :filter', ['filter' => $applied['label']]) }}"
                        >
                            <flux:icon.x-mark variant="micro" class="size-3.5" aria-hidden="true" />
                        </button>
                    </x-chip>
                @endforeach

                @if (count($appliedFilters) > 0)
                    <button type="button" wire:click="resetFilters" class="shrink-0 whitespace-nowrap px-1 text-sm font-medium">
                        <span class="text-sunset-small hover:underline">{{ __('Clear all') }}</span>
                    </button>
                @endif
            </div>

            @if ($canCreateAlert)
                <x-card padding="sm" class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <x-icon-tile icon="bell" size="sm" />
                    <p class="min-w-0 flex-1 text-sm text-ink-soft">{{ __('Get an email when new jobs match this search.') }}</p>
                    <flux:button :href="$alertUrl" size="sm">{{ __('Create job alert') }}</flux:button>
                </x-card>
            @endif

            {{-- Dimmed while new results load, so a changed filter is seen
                 to have done something. --}}
            <div class="mt-6 transition-opacity" wire:loading.delay.class="opacity-50">
                @if ($jobPostings->isEmpty())
                    @if ($activeFilterCount > 0 || $searching)
                        <x-empty-state icon="magnifying-glass" :heading="__('No openings match this search')">
                            {{ $activeFilterCount > 0 ? __('Try removing a filter, or searching with other words.') : __('Try other words, or a nearby city.') }}
                            @if ($activeFilterCount > 0)
                                <x-slot:actions>
                                    <flux:button variant="primary" size="sm" wire:click="resetFilters">{{ __('Clear filters') }}</flux:button>
                                </x-slot:actions>
                            @endif
                        </x-empty-state>
                    @elseif (isset($categoryModel))
                        <x-empty-state icon="briefcase" :heading="__('No open :category positions right now', ['category' => $categoryModel->name])" :action-href="route('jobs.index')" :action-label="__('Browse all open roles')">
                            {{ __('Check back soon, or look through the roles open in every field.') }}
                        </x-empty-state>
                    @else
                        <x-empty-state icon="briefcase" :heading="__('No open roles right now')">
                            {{ __('New roles appear here as soon as employers publish them.') }}
                        </x-empty-state>
                    @endif
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($jobPostings as $jobPosting)
                            <x-job-card
                                :job-posting="$jobPosting"
                                :match-score="$matchScores[$jobPosting->id] ?? null"
                                :show-save-button="$canSave"
                                :saved="in_array($jobPosting->id, $savedJobIds, true)"
                                wire:key="job-{{ $jobPosting->id }}"
                            />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $jobPostings->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
