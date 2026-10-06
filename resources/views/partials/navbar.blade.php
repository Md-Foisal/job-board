{{--
    Shared Shell A navbar: guest pages
    and the candidate account pages carry the exact same navbar -- account
    pages just add a local sidebar alongside it (layouts/app/sidebar.blade.php).
    Keeping this in one partial is what makes that guarantee real instead of
    two copies quietly drifting apart.

    It sits above everything that scrolls under it (z-20): cards lift their
    own links to z-10 inside the page, and the mobile sidebar drawer, which
    comes later in the document at the same level, still covers it.
--}}
@php
    $showSidebarToggle ??= false;
@endphp

<nav class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-canvas/90 px-4 py-3 backdrop-blur-md sm:gap-4 sm:px-6">
    <div class="flex shrink-0 items-center gap-2">
        @if ($showSidebarToggle)
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />
        @endif

        <a href="{{ route('home') }}" class="rounded-control" wire:navigate>
            <x-logo />
        </a>
    </div>

    <div class="hidden flex-1 items-center gap-2 md:flex">
        {{-- The homepage, the job search and the category pages carry
             their own search box right below this nav, so a second one
             here would only compete with it. --}}
        {{-- No standalone categories menu here by design: real job boards keep
             the navbar search-first and treat category browsing as secondary.
             Category access still exists via the homepage "browse by category"
             grid and as an in-search filter (FiltersJobPostings::$category). --}}
        @unless (request()->routeIs('home', 'jobs.index', 'categories.show'))
            <livewire:job-search-autocomplete variant="compact" />
        @endunless
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <x-theme-toggle />

        @guest
            {{-- Employers have their own front door, as on every large
                 board; it explains the hiring side before asking for an
                 account. --}}
            <a href="{{ route('employers') }}" class="hidden rounded-control px-2 py-1.5 text-sm font-medium text-ink-muted transition-colors hover:text-ink sm:inline" wire:navigate>
                {{ __('For employers') }}
            </a>
            <a href="{{ route('login') }}" class="hidden rounded-control px-2 py-1.5 text-sm font-medium text-ink-muted transition-colors hover:text-ink sm:inline">
                {{ __('Log in') }}
            </a>
            <flux:button :href="route('register')" variant="primary" size="sm">
                {{ __('Sign up') }}
            </flux:button>

            {{-- On a phone the links above do not fit beside the logo, so
                 they move into a menu; signing up stays in view. --}}
            <flux:dropdown position="bottom" align="end" class="sm:hidden">
                <flux:button variant="ghost" size="sm" icon="bars-3" :aria-label="__('Menu')" />

                <flux:menu class="min-w-52">
                    <flux:menu.item :href="route('jobs.index')" icon="magnifying-glass" wire:navigate>
                        {{ __('Browse jobs') }}
                    </flux:menu.item>
                    <flux:menu.item :href="route('employers')" icon="building-office-2" wire:navigate>
                        {{ __('For employers') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('login')" icon="arrow-right-end-on-rectangle">
                        {{ __('Log in') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endguest

        @auth
            {{-- Inside the sidebar shell the sidebar already leads with
                 Dashboard. Elsewhere this skips the /dashboard redirect
                 dispatcher (routes/web.php) when we already know where a
                 candidate is headed. --}}
            @unless ($showSidebarToggle)
                <a href="{{ auth()->user()->isCandidate() ? route('candidate.dashboard') : route('dashboard') }}" class="hidden rounded-control px-2 py-1.5 text-sm font-medium text-ink-muted transition-colors hover:text-ink sm:inline" wire:navigate>
                    {{ __('Dashboard') }}
                </a>
            @endunless

            @include('partials.account-menu')
        @endauth
    </div>
</nav>
