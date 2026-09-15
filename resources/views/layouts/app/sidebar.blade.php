{{-- An anonymous component only receives passed data as variables when it
     declares them, so without this the :title layouts/app.blade.php passes
     in never reached partials.head and every page fell back to the bare
     app name in the browser tab. --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @include('partials.navbar', ['showSidebarToggle' => true])

    <div class="flex flex-1">
        <flux:sidebar collapsible="mobile" sticky
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    {{-- Candidates land on their real dashboard directly rather than
                         bouncing through the generic /dashboard redirect dispatcher
                         (routes/web.php) -- same destination, one less hop. --}}
                    <flux:sidebar.item icon="home"
                        :href="auth()->check() && auth()->user()->isCandidate() ? route('candidate.dashboard') : route('dashboard')"
                        :current="request()->routeIs('dashboard') || request()->routeIs('candidate.dashboard')"
                        wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @auth
                    @if (auth()->user()->isCandidate())
                        <flux:sidebar.group :heading="__('Candidate')" class="grid">
                            <flux:sidebar.item icon="user" :href="route('candidate.profile.edit')"
                                :current="request()->routeIs('candidate.profile.*')" wire:navigate>
                                {{ __('Profile') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('candidate.preferences.edit')"
                                :current="request()->routeIs('candidate.preferences.*')" wire:navigate>
                                {{ __('Preferences') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="academic-cap" :href="route('candidate.education.index')"
                                :current="request()->routeIs('candidate.education.*')" wire:navigate>
                                {{ __('Education') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="briefcase" :href="route('candidate.experience.index')"
                                :current="request()->routeIs('candidate.experience.*')" wire:navigate>
                                {{ __('Experience') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="document-text" :href="route('candidate.documents.index')"
                                :current="request()->routeIs('candidate.documents.*')" wire:navigate>
                                {{ __('Documents') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="tag" :href="route('candidate.skills.edit')"
                                :current="request()->routeIs('candidate.skills.*')" wire:navigate>
                                {{ __('Skills') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="paper-airplane" :href="route('candidate.applications.index')"
                                :current="request()->routeIs('candidate.applications.*')" wire:navigate>
                                {{ __('My Applications') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="bookmark" :href="route('candidate.saved-jobs.index')"
                                :current="request()->routeIs('candidate.saved-jobs.*')" wire:navigate>
                                {{ __('Saved Jobs') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-w-0 flex-1 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    {{-- Plain (non-Livewire) controller redirects flash session('success'/'error')
         data. Flux::toast() only reaches a <flux:toast> from inside a Livewire
         request, which a plain controller redirect can't do -- but Flux also
         ships a standalone JS API (window.Flux.toast) that works anywhere once
         Alpine has booted, so a flashed message here just calls that instead of
         a bespoke banner (same fix as layouts/guest.blade.php). --}}
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
