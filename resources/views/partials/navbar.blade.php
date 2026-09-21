{{--
    Shared Shell A navbar: guest pages
    and the candidate account pages carry the exact same navbar -- account
    pages just add a local sidebar alongside it (layouts/app/sidebar.blade.php).
    Keeping this in one partial is what makes that guarantee real instead of
    two copies quietly drifting apart.
--}}
@php($showSidebarToggle ??= false)

<nav class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-zinc-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-950">
    <div class="flex shrink-0 items-center gap-3">
        @if ($showSidebarToggle)
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />
        @endif

        <a href="{{ route('home') }}" class="font-display text-lg font-bold text-brand-700 dark:text-brand-400" wire:navigate>
            JobBoard
        </a>
    </div>

    <div class="hidden flex-1 items-center gap-2 md:flex">
        {{-- The homepage carries its own large hero search right below
             this nav, so repeating it here would just be noise --}}
        {{-- No standalone categories menu here by design: real job boards keep
             the navbar search-first and treat category browsing as secondary.
             Category access still exists via the homepage "browse by category"
             grid and as an in-search filter (FiltersJobPostings::$category). --}}
        @unless (request()->routeIs('home'))
            <livewire:job-search-autocomplete variant="compact" />
        @endunless
    </div>

    <div class="flex shrink-0 items-center gap-4">
        <x-theme-toggle />

        @guest
            <a href="{{ route('register') }}" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">For Employers</a>
            <a href="{{ route('login') }}" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">Log in</a>
            <a href="{{ route('register') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Sign up
            </a>
        @endguest

        @auth
            {{-- Same direct-link optimization as the candidate sidebar's Platform
                 group: skip the /dashboard redirect dispatcher (routes/web.php)
                 when we already know where a candidate is headed. --}}
            <a href="{{ auth()->user()->isCandidate() ? route('candidate.dashboard') : route('dashboard') }}" class="hidden text-sm text-zinc-600 hover:text-brand-700 sm:inline dark:text-zinc-400 dark:hover:text-brand-400" wire:navigate>
                Dashboard
            </a>

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="cursor-pointer" aria-label="{{ __('Account menu') }}">
                    <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                </button>

                <flux:menu>
                    <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    @if (auth()->user()->isCandidate())
                        <flux:menu.item :href="route('candidate.profile.edit')" icon="user" wire:navigate>
                            {{ __('My profile') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        @endauth
    </div>
</nav>
