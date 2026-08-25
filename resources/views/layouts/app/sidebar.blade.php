<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit"
                target="_blank">
                {{ __('Repository') }}
            </flux:sidebar.item>

            <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire"
                target="_blank">
                {{ __('Documentation') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

        @auth
            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        @endauth
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        @auth
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        @endauth
    </flux:header>

    {{-- Phase 4: plain (non-Livewire) controllers like JobListingController
    redirect back with session('error'/'success') flash data (e.g. the
    job-listing deletion guard). This layout's <flux:toast> only reacts to
    Flux::toast() calls dispatched from within a Livewire request, which a
    plain controller redirect can't reach -- so those flashes need their own
    simple banner here instead of silently going nowhere. --}}
    @if (session('success'))
        <div class="max-w-4xl mx-auto mt-4 px-4">
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-700 dark:text-green-300">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-4xl mx-auto mt-4 px-4">
            <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{ $slot }}

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>