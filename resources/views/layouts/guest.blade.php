<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @include('partials.navbar')

    @include('partials.flash-toasts')

    <main>
        {{ $slot }}
    </main>

    @persist('toast')
    <flux:toast.group position="top end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    {{-- Shell A's footer, the supplemental navigation.
         It is the only place the static pages are reachable
         from, which is why they are links here and not just a line of
         copyright. --}}
    <footer class="mt-16 border-t border-zinc-200 px-6 py-8 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4">
            <p>&copy; {{ now()->year }} JobBoard.</p>

            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <a href="{{ route('jobs.index') }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>{{ __('Browse jobs') }}</a>
                <a href="{{ route('about') }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>{{ __('About') }}</a>
                <a href="{{ route('privacy') }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>{{ __('Privacy') }}</a>
                <a href="{{ route('terms') }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>{{ __('Terms') }}</a>
            </nav>
        </div>
    </footer>

    {{--
        @fluxScripts (not @livewireScripts) is what every other layout in
        this app uses, and it's required here too: it forces Livewire's
        asset injection so Alpine boots even on pages with zero
        <livewire:...> components (the plain-controller home page, this
        layout's own Alpine-driven theme toggle), AND it loads Flux's own
        JS bundle, which is what actually makes flux:dropdown, flux:menu
        and flux:modal (Categories menu, the report button, etc.)
        clickable/interactive -- @livewireScripts alone boots Alpine but
        never loads that Flux behavior layer.
    --}}
    @fluxScripts
</body>
</html>
