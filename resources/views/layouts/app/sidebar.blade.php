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

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    @include('partials.svg-defs')
    @include('partials.navbar', ['showSidebarToggle' => true])

    <div class="flex flex-1">
        {{-- Grouped by the two jobs a candidate comes here for: looking for
             work, and keeping the profile that applications are made from.
             Every page of the profile has its own entry as well as a link
             from the profile overview, so each is one click from anywhere.
             It can be narrowed to icons on a desktop, but opens wide: the
             labels are what make it quick to scan. --}}
        <flux:sidebar collapsible sticky
            class="border-e border-line bg-surface">
            <flux:sidebar.header class="justify-end">
                <flux:sidebar.collapse :tooltip="__('Collapse sidebar')" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                {{-- Candidates land on their real dashboard directly rather than
                     bouncing through the generic /dashboard redirect dispatcher
                     (routes/web.php) -- same destination, one less hop. --}}
                <flux:sidebar.item icon="home"
                    :href="auth()->check() && auth()->user()->isCandidate() ? route('candidate.dashboard') : route('dashboard')"
                    :current="request()->routeIs('dashboard') || request()->routeIs('candidate.dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @auth
                    @if (auth()->user()->isCandidate())
                        <x-sidebar-heading>{{ __('Job search') }}</x-sidebar-heading>

                        {{-- The top bar's search box is hidden on phones, so
                             this is the way back to the jobs from here. --}}
                        <flux:sidebar.item icon="magnifying-glass" :href="route('jobs.index')" wire:navigate>
                            {{ __('Find jobs') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="paper-airplane" :href="route('candidate.applications.index')"
                            :current="request()->routeIs('candidate.applications.*')" wire:navigate>
                            {{ __('Applications') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="bookmark" :href="route('candidate.saved-jobs.index')"
                            :current="request()->routeIs('candidate.saved-jobs.*')" wire:navigate>
                            {{ __('Saved jobs') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="bell" :href="route('candidate.job-alerts.index')"
                            :current="request()->routeIs('candidate.job-alerts.*')" wire:navigate>
                            {{ __('Job alerts') }}
                        </flux:sidebar.item>

                        <x-sidebar-heading>{{ __('Profile') }}</x-sidebar-heading>

                        <flux:sidebar.item icon="user-circle" :href="route('candidate.profile.edit')"
                            :current="request()->routeIs('candidate.profile.*') || request()->routeIs('candidate.resume-import')" wire:navigate>
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="briefcase" :href="route('candidate.experience.index')"
                            :current="request()->routeIs('candidate.experience.*')" wire:navigate>
                            {{ __('Experience') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="academic-cap" :href="route('candidate.education.index')"
                            :current="request()->routeIs('candidate.education.*')" wire:navigate>
                            {{ __('Education') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('candidate.skills.edit')"
                            :current="request()->routeIs('candidate.skills.*')" wire:navigate>
                            {{ __('Skills') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('candidate.documents.index')"
                            :current="request()->routeIs('candidate.documents.*')" wire:navigate>
                            {{ __('Documents') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-plus" :href="route('candidate.cv-builder')"
                            :current="request()->routeIs('candidate.cv-builder')" wire:navigate>
                            {{ __('CV builder') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('candidate.preferences.edit')"
                            :current="request()->routeIs('candidate.preferences.*')" wire:navigate>
                            {{ __('Job preferences') }}
                        </flux:sidebar.item>
                    @endif

                    <x-sidebar-heading>{{ __('Account') }}</x-sidebar-heading>

                    <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')"
                        :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                @endauth
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
