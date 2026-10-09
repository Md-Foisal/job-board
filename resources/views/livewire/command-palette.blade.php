{{--
    The command palette (App\Livewire\CommandPalette). A native <dialog>
    opened as a modal, so the page behind it is inert, focus stays inside
    and Escape closes it without any code of ours. Inside, the WAI-ARIA
    combobox pattern: focus never leaves the text box, the arrow keys move
    a highlighted option (aria-activedescendant), Enter opens it.

    Pages are filtered here in the browser, so they answer on the first
    letter; the searches go to the server a moment after typing stops.
--}}
@php
    $recentHeading = $company ? __('Recent job postings') : __('Recently viewed');
    $searchesJobs = $company === null;
    $groupIndex = 0;
@endphp

<div
    x-data="{
        query: '',
        active: 0,
        count: 0,
        activeId: null,
        observer: null,

        init() {
            // Server results arrive by morphing the list; the highlight
            // follows whatever is shown now.
            this.observer = new MutationObserver(() => this.mark());
            this.observer.observe(this.$refs.list, { childList: true, subtree: true });
        },

        destroy() {
            this.observer?.disconnect();
        },

        shortcut(event) {
            if ((event.metaKey || event.ctrlKey) && ! event.altKey && ! event.shiftKey && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.$refs.dialog.open ? this.close() : this.open();
            }
        },

        open() {
            if (this.$refs.dialog.open) {
                return;
            }

            this.$refs.dialog.showModal();
            this.$refs.input.focus();
            this.$nextTick(() => this.mark());

            if (! this.$wire.ready) {
                this.$wire.set('ready', true);
            }
        },

        close() {
            this.$refs.dialog.close();
        },

        closed() {
            if (this.query !== '') {
                this.query = '';
                this.$wire.set('q', '');
            }

            this.active = 0;
        },

        typed() {
            this.active = 0;
            this.$nextTick(() => this.mark());
        },

        matches(text) {
            const words = this.query.toLowerCase().split(/\s+/).filter(Boolean);
            const haystack = text.toLowerCase();

            return words.every((word) => haystack.includes(word));
        },

        options() {
            return [...this.$refs.list.querySelectorAll('[data-palette-option]')]
                .filter((el) => el.getClientRects().length > 0);
        },

        mark() {
            const options = this.options();
            this.count = options.length;
            this.active = options.length ? Math.min(this.active, options.length - 1) : 0;

            options.forEach((el, index) => el.setAttribute('aria-selected', index === this.active ? 'true' : 'false'));
            this.$refs.list.querySelectorAll('[data-palette-option]').forEach((el) => {
                if (el.getClientRects().length === 0) {
                    el.setAttribute('aria-selected', 'false');
                }
            });

            const current = options[this.active];
            this.activeId = current ? current.id : null;
            current?.scrollIntoView({ block: 'nearest' });
        },

        move(step) {
            const total = this.options().length;

            if (total === 0) {
                return;
            }

            this.active = (this.active + step + total) % total;
            this.mark();
        },

        hover(el) {
            const index = this.options().indexOf(el);

            if (index !== -1 && index !== this.active) {
                this.active = index;
                this.mark();
            }
        },

        go() {
            const option = this.options()[this.active];

            if (option) {
                this.close();
                option.click();
            }
        },
    }"
    x-on:keydown.window="shortcut($event)"
    x-on:open-command-palette.window="open()"
>
    {{-- wire:ignore.self: the dialog's open attribute is the browser's,
         and a server update must not take it away mid-search. --}}
    <dialog
        x-ref="dialog"
        wire:ignore.self
        x-on:close="closed()"
        x-on:click="if ($event.target === $refs.dialog) close()"
        aria-label="{{ __('Search and jump to a page') }}"
        class="m-0 mx-auto mt-[10dvh] w-[calc(100%-2rem)] max-w-xl overflow-visible bg-transparent p-0 text-ink backdrop:bg-black/40 backdrop:backdrop-blur-[2px] sm:mt-[14dvh]"
    >
        <div class="overflow-hidden rounded-card border border-line bg-canvas shadow-lift">
            <div class="flex items-center gap-3 border-b border-line px-4">
                <flux:icon.magnifying-glass class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />

                <input
                    x-ref="input"
                    x-model="query"
                    x-on:input="typed()"
                    x-on:input.debounce.250ms="$wire.set('q', query)"
                    x-on:keydown.down.prevent="move(1)"
                    x-on:keydown.up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="go()"
                    type="text"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls="command-palette-list"
                    aria-autocomplete="list"
                    :aria-activedescendant="activeId"
                    autofocus
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="{{ $searchesJobs ? __('Search jobs or jump to a page') : __('Search applicants, jobs or pages') }}"
                    aria-label="{{ $searchesJobs ? __('Search jobs or jump to a page') : __('Search applicants, jobs or pages') }}"
                    class="h-14 min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:outline-none"
                >

                {{-- Where the search looks, as GitHub's palette shows its scope. --}}
                <span class="hidden max-w-36 shrink-0 truncate rounded-full bg-surface px-2.5 py-1 text-meta font-medium text-ink-muted sm:inline">{{ $scope }}</span>

                <button type="button" x-on:click="close()" class="shrink-0 rounded-control p-1 text-ink-muted transition-colors hover:text-ink" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" aria-hidden="true" />
                </button>
            </div>

            <div
                x-ref="list"
                id="command-palette-list"
                role="listbox"
                aria-label="{{ __('Results') }}"
                class="max-h-[60dvh] overflow-y-auto overscroll-contain p-2"
            >
                {{-- Recent: only before anything is typed. --}}
                <div x-show="query.trim() === ''">
                    @if (! $ready)
                        <div class="space-y-2 px-3 py-2" aria-hidden="true">
                            <flux:skeleton animate="shimmer" class="h-3 w-24" />
                            <flux:skeleton animate="shimmer" class="h-8 w-full" />
                            <flux:skeleton animate="shimmer" class="h-8 w-4/5" />
                        </div>
                    @elseif ($this->recent->isNotEmpty())
                        <div role="group" aria-labelledby="command-palette-recent">
                            <div id="command-palette-recent" class="px-3 pt-2 pb-1.5 text-meta font-medium text-ink-muted">{{ $recentHeading }}</div>
                            @foreach ($this->recent as $entry)
                                @include('livewire.partials.command-palette-option', ['id' => 'command-palette-recent-'.$loop->index, 'entry' => $entry])
                            @endforeach
                        </div>
                    @endif
                </div>

                <div role="group" aria-labelledby="command-palette-pages">
                    <div id="command-palette-pages" class="px-3 pt-2 pb-1.5 text-meta font-medium text-ink-muted" x-show="@js(collect($this->pages)->map(fn ($page) => $page['label'].' '.$page['section'])->values()).some((text) => matches(text))">{{ __('Pages') }}</div>
                    @foreach ($this->pages as $page)
                        <div x-show="matches(@js($page['label'].' '.$page['section']))">
                            @include('livewire.partials.command-palette-option', [
                                'id' => 'command-palette-page-'.$loop->index,
                                'entry' => [
                                    'label' => $page['label'],
                                    'meta' => $page['section'],
                                    'url' => $page['item']->url,
                                    'icon' => $page['item']->icon,
                                ],
                            ])
                        </div>
                    @endforeach
                </div>

                <div x-show="query.trim().length >= {{ \App\Livewire\CommandPalette::MIN_QUERY_LENGTH }}">
                    <div wire:loading.delay wire:target="q" class="px-3 py-2 text-sm text-ink-muted">{{ __('Searching…') }}</div>

                    <div wire:loading.remove.delay wire:target="q">
                        @foreach ($this->results as $heading => $entries)
                            @php $groupIndex++; @endphp
                            <div role="group" aria-labelledby="command-palette-group-{{ $groupIndex }}" wire:key="command-palette-group-{{ $heading }}">
                                <div id="command-palette-group-{{ $groupIndex }}" class="px-3 pt-2 pb-1.5 text-meta font-medium text-ink-muted">{{ $heading }}</div>
                                @foreach ($entries as $entry)
                                    @include('livewire.partials.command-palette-option', ['id' => 'command-palette-result-'.$groupIndex.'-'.$loop->index, 'entry' => $entry])
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    {{-- The full search is always one step away, with its filters. --}}
                    @if ($searchesJobs)
                        <a
                            id="command-palette-search-all"
                            data-palette-option
                            role="option"
                            aria-selected="false"
                            :href="@js(route('jobs.index')) + '?q=' + encodeURIComponent(query.trim())"
                            wire:navigate
                            x-on:mousemove="hover($el)"
                            x-on:click="close()"
                            class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm text-ink transition-colors aria-selected:bg-surface"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-control border border-line bg-canvas">
                                <flux:icon.magnifying-glass class="size-4 text-ink-muted" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 truncate">{{ __('Search all jobs for') }} <span class="font-medium" x-text="'“' + query.trim() + '”'"></span></span>
                        </a>
                    @endif
                </div>

                <div wire:loading.remove.delay wire:target="q">
                    <p x-show="query.trim() !== '' && count === 0" class="px-3 py-8 text-center text-sm text-ink-muted">
                        {{ __('Nothing matches') }} <span class="font-medium text-ink" x-text="'“' + query.trim() + '”'"></span>
                    </p>
                </div>
            </div>

            {{-- Keyboard help, only where there is a keyboard to use. --}}
            <div class="hidden items-center gap-4 border-t border-line bg-surface px-4 py-2.5 text-meta text-ink-muted [@media(hover:hover)_and_(pointer:fine)]:flex" aria-hidden="true">
                <span class="inline-flex items-center gap-1.5"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> {{ __('to move') }}</span>
                <span class="inline-flex items-center gap-1.5"><kbd class="kbd">↵</kbd> {{ __('to open') }}</span>
                <span class="inline-flex items-center gap-1.5"><kbd class="kbd">Esc</kbd> {{ __('to close') }}</span>
            </div>
        </div>
    </dialog>
</div>
