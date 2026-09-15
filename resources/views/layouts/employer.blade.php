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
@php($company ??= request()->route('company'))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @include('partials.employer-topbar', ['company' => $company])

    <div class="flex flex-1">
        <flux:sidebar collapsible="mobile" sticky
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('General')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('employer.dashboard', $company)"
                        :current="request()->routeIs('employer.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @can('update', $company)
                    <flux:sidebar.group :heading="__('Company')" class="grid">
                        <flux:sidebar.item icon="building-office" :href="route('employer.company.edit', $company)"
                            :current="request()->routeIs('employer.company.*')" wire:navigate>
                            {{ __('Company profile') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('employer.team.index', $company)"
                            :current="request()->routeIs('employer.team.*')" wire:navigate>
                            {{ __('Team') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                <flux:sidebar.group :heading="__('Hiring')" class="grid">
                    <flux:sidebar.item icon="briefcase" :href="route('employer.jobs.index', $company)"
                        :current="request()->routeIs('employer.jobs.*')" wire:navigate>
                        {{ __('Job postings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Outside the Company group on purpose: this one is the
                     person's own, not the company's, and every member has
                     it regardless of rank. --}}
                <flux:sidebar.group :heading="__('You')" class="grid">
                    <flux:sidebar.item icon="identification" :href="route('employer.recruiter-profile.edit')"
                        :current="request()->routeIs('employer.recruiter-profile.*')" wire:navigate>
                        {{ __('Recruiter profile') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-w-0 flex-1 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    {{-- Same flashed-message bridge as the candidate shell: a plain
         controller redirect cannot reach <flux:toast> through Livewire,
         so it goes through Flux's standalone JS API instead. --}}
    @if (session('success'))
        <script>
            document.addEventListener('alpine:init', () => {
                setTimeout(() => {
                    Flux.toast({ text: @js(session('success')), variant: 'success' });
                });
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            document.addEventListener('alpine:init', () => {
                setTimeout(() => {
                    Flux.toast({ text: @js(session('error')), variant: 'danger' });
                });
            });
        </script>
    @endif

    @persist('toast')
    <flux:toast.group position="top end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
