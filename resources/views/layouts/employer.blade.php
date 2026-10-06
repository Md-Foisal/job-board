{{--
    Shell C -- the company workspace.

    Every page inside it is about one company, and that company arrives as
    a required prop rather than being read from the session, so the layout
    can never render for a different company than the URL asked for.

    Sidebar items are added by the pages that own them; a link here to a
    route that does not exist yet is just a broken link. Applications
    review is intentionally absent: it belongs to one job posting, not to
    the company, so it is reached from the job listing rather than from a
    permanent nav entry.
--}}
@props(['company' => null, 'title' => null])

{{-- Blade pages hand the company in explicitly, which keeps the dependency
     visible at the call site. A Livewire full-page component cannot: its
     #[Layout] attribute only takes constants. Falling back to the route
     parameter costs nothing in correctness -- every page in this shell is
     behind the {company:slug} prefix and the membership guard, so the URL
     is already the authority on which company this is -- and the layout
     only renders on the first load, never on a Livewire update. --}}
@php
    // Block form on purpose: the inline @php(...) directive compiles to an
    // unterminated <?php( ... ) when this layout is rendered through
    // Livewire's #[Layout] path, which then swallows the markup after it.
    $company ??= request()->route('company');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    @include('partials.svg-defs')
    @include('partials.employer-topbar', ['company' => $company])

    <div class="flex flex-1">
        <flux:sidebar collapsible sticky
            class="border-e border-line bg-surface">
            <flux:sidebar.header class="justify-end">
                <flux:sidebar.collapse :tooltip="__('Collapse sidebar')" />
            </flux:sidebar.header>

            {{-- The one thing an employer comes to do most, kept in reach
                 from every page of the workspace, as large boards do; only
                 for those whose role lets them post. --}}
            @can('create', [\App\Models\JobPosting::class, $company])
                <flux:button :href="route('employer.jobs.create', $company)" variant="primary" icon="plus" class="w-full in-data-flux-sidebar-collapsed-desktop:hidden" wire:navigate>
                    {{ __('Post a job') }}
                </flux:button>
                <div class="hidden in-data-flux-sidebar-collapsed-desktop:block">
                    <flux:sidebar.item icon="plus" :href="route('employer.jobs.create', $company)" wire:navigate>
                        {{ __('Post a job') }}
                    </flux:sidebar.item>
                </div>
            @endcan

            {{-- Daily work first, company housekeeping after it. --}}
            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('employer.dashboard', $company)"
                    :current="request()->routeIs('employer.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <x-sidebar-heading>{{ __('Hiring') }}</x-sidebar-heading>

                <flux:sidebar.item icon="briefcase" :href="route('employer.jobs.index', $company)"
                    :current="request()->routeIs('employer.jobs.*') || request()->routeIs('employer.applications.*')" wire:navigate>
                    {{ __('Job postings') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" :href="route('employer.analytics', $company)"
                    :current="request()->routeIs('employer.analytics')" wire:navigate>
                    {{ __('Analytics') }}
                </flux:sidebar.item>
                {{-- The count is for those who can answer: to a plain
                     member it would be a to-do they cannot do. --}}
                @php
                    $reviewsAwaiting = auth()->user()?->canManage($company)
                        ? $company->reviews()->published()->awaitingResponse()->count()
                        : 0;
                @endphp
                <flux:sidebar.item icon="chat-bubble-left-right" :href="route('employer.reviews', $company)"
                    :current="request()->routeIs('employer.reviews')" :badge="$reviewsAwaiting > 0 ? $reviewsAwaiting : null" wire:navigate>
                    {{ __('Reviews') }}
                </flux:sidebar.item>

                @can('update', $company)
                    <x-sidebar-heading>{{ __('Company') }}</x-sidebar-heading>

                    <flux:sidebar.item icon="building-office" :href="route('employer.company.edit', $company)"
                        :current="request()->routeIs('employer.company.*')" wire:navigate>
                        {{ __('Company profile') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('employer.team.index', $company)"
                        :current="request()->routeIs('employer.team.*')" wire:navigate>
                        {{ __('Team') }}
                    </flux:sidebar.item>
                @endcan

                {{-- Apart from the Company section on purpose: these are the
                     person's own, not the company's, and every member has
                     them regardless of rank. --}}
                <x-sidebar-heading>{{ __('You') }}</x-sidebar-heading>

                {{-- The workspace it was opened from travels along, so the
                     page keeps the company you were in instead of jumping to
                     another one of yours. --}}
                <flux:sidebar.item icon="identification" :href="route('employer.recruiter-profile.edit', ['company' => $company->slug])"
                    :current="request()->routeIs('employer.recruiter-profile.*')" wire:navigate>
                    {{ __('Recruiter profile') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-w-0 flex-1 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @include('partials.flash-toasts')

    @persist('toast')
    <flux:toast.group position="bottom end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
