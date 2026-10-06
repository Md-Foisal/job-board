<div
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative {{ $variant === 'hero' ? 'w-full max-w-2xl' : 'w-full max-w-sm' }}"
>
    @if ($variant === 'hero')
        {{-- What and where, the two questions every large board asks first. --}}
        <form wire:submit="goToSearch" class="flex flex-col gap-1.5 rounded-2xl border border-line bg-canvas p-1.5 text-left shadow-lift transition-shadow focus-within:border-line-strong sm:flex-row">
            <label class="flex min-h-12 flex-1 items-center gap-2.5 rounded-xl px-3.5 focus-within:bg-surface">
                <flux:icon.magnifying-glass class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                <span class="sr-only">{{ __('Job title, skill or company') }}</span>
                <input
                    type="search"
                    wire:model.live.debounce.400ms="q"
                    x-on:input="open = $event.target.value.trim().length > 0"
                    x-on:focus="open = $event.target.value.trim().length > 0"
                    placeholder="{{ __('Job title, skill or company') }}"
                    autocomplete="off"
                    class="w-full min-w-0 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:outline-none"
                >
            </label>

            <label class="flex min-h-12 items-center gap-2.5 rounded-xl border-t border-line px-3.5 focus-within:bg-surface sm:w-48 sm:border-t-0 sm:border-l">
                <flux:icon.map-pin class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                <span class="sr-only">{{ __('City, or remote') }}</span>
                <input
                    type="text"
                    wire:model="where"
                    placeholder="{{ __('City or remote') }}"
                    autocomplete="off"
                    class="w-full min-w-0 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:outline-none"
                >
            </label>

            <button type="submit" class="btn-sunset min-h-12 rounded-xl px-6 text-body font-semibold">
                {{ __('Search jobs') }}
            </button>
        </form>
    @else
        <form wire:submit="goToSearch">
            <flux:input
                type="search"
                wire:model.live.debounce.400ms="q"
                x-on:input="open = $event.target.value.trim().length > 0"
                x-on:focus="open = $event.target.value.trim().length > 0"
                placeholder="{{ __('Search jobs...') }}"
                aria-label="{{ __('Search jobs') }}"
                icon="magnifying-glass"
                autocomplete="off"
                class="w-full"
            />
        </form>
    @endif

    <div
        x-show="open"
        x-cloak
        class="absolute z-30 mt-2 w-full overflow-hidden rounded-control border border-line bg-canvas text-left shadow-lift"
    >
        <div wire:loading.delay wire:target="q" class="px-4 py-3 text-sm text-ink-muted">
            {{ __('Searching…') }}
        </div>

        <div wire:loading.remove.delay wire:target="q">
            @if ($q !== '')
                @if ($this->results->isNotEmpty())
                    @foreach ($this->results as $jobPosting)
                        <a
                            href="{{ route('jobs.show', $jobPosting) }}"
                            wire:navigate
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-surface"
                        >
                            <span class="truncate font-medium text-ink">{{ $jobPosting->title }}</span>
                            <span class="shrink-0 truncate text-meta text-ink-muted">{{ $jobPosting->company->name }}</span>
                        </a>
                    @endforeach

                    <button
                        type="button"
                        wire:click="goToSearch"
                        class="block w-full border-t border-line px-4 py-2.5 text-left text-sm font-medium transition-colors hover:bg-surface"
                    >
                        <span class="text-sunset-small">{{ __('See all results for “:query”', ['query' => $q]) }} &rarr;</span>
                    </button>
                @else
                    <p class="px-4 py-3 text-sm text-ink-muted">
                        {{ __('No quick matches — press Enter to search all listings.') }}
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>
