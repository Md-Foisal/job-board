<div
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative {{ $variant === 'hero' ? 'mx-auto w-full max-w-xl' : 'w-full max-w-sm' }}"
>
    <form wire:submit="goToSearch" class="{{ $variant === 'hero' ? 'flex items-stretch gap-2' : '' }}">
        <flux:input
            type="search"
            wire:model.live.debounce.400ms="q"
            x-on:input="open = $event.target.value.trim().length > 0"
            x-on:focus="open = $event.target.value.trim().length > 0"
            placeholder="{{ $variant === 'hero' ? 'Job title, skill, or company' : 'Search jobs...' }}"
            aria-label="{{ __('Search jobs') }}"
            icon="magnifying-glass"
            autocomplete="off"
            class="{{ $variant === 'hero' ? 'grow' : 'w-full' }}"
        />

        @if ($variant === 'hero')
            <flux:button type="submit" variant="primary">Search</flux:button>
        @endif
    </form>

    <div
        x-show="open"
        x-cloak
        class="absolute z-20 mt-2 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white text-left shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
    >
        <div wire:loading.delay wire:target="q" class="px-4 py-3 text-sm text-zinc-400 dark:text-zinc-500">
            Searching&hellip;
        </div>

        <div wire:loading.remove.delay wire:target="q">
            @if ($q !== '')
                @if ($this->results->isNotEmpty())
                    @foreach ($this->results as $jobPosting)
                        <a
                            href="{{ route('jobs.show', $jobPosting) }}"
                            wire:navigate
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition hover:bg-brand-50 dark:hover:bg-brand-950"
                        >
                            <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $jobPosting->title }}</span>
                            <span class="shrink-0 truncate text-xs text-zinc-500 dark:text-zinc-500">{{ $jobPosting->company->name }}</span>
                        </a>
                    @endforeach

                    <button
                        type="button"
                        wire:click="goToSearch"
                        class="block w-full border-t border-zinc-100 px-4 py-2.5 text-left text-sm font-medium text-brand-700 transition hover:bg-brand-50 dark:border-zinc-800 dark:text-brand-400 dark:hover:bg-brand-950"
                    >
                        See all results for &ldquo;{{ $q }}&rdquo; &rarr;
                    </button>
                @else
                    <p class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-500">
                        No quick matches &mdash; press Enter to search all listings.
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>
