{{-- "errorPage" is set by error pages that keep the site's own frame
     (components/error-page), so they can load Livewire themselves. --}}
@props(['title' => null, 'errorPage' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    @if ($errorPage)
        {{-- Injected only into 200 responses too; without it the navbar
             search's loading spinner shows for good. --}}
        @livewireStyles
    @endif
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    @include('partials.svg-defs')
    @include('partials.navbar')

    @include('partials.flash-toasts')

    <main>
        {{ $slot }}
    </main>

    @persist('toast')
    <flux:toast.group position="bottom end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    {{-- Shell A's footer, the supplemental navigation. One column for
         each audience the board serves, then the board itself; the static
         pages are reachable from nowhere else, which is why they are here
         as links and not just a line of copyright. --}}
    <footer class="mt-20 border-t border-line bg-surface">
        <div class="mx-auto grid max-w-6xl gap-10 px-6 py-12 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
            <div class="flex flex-col gap-3 sm:col-span-2 lg:col-span-1">
                <a href="{{ route('home') }}" class="w-fit rounded-control" wire:navigate>
                    <x-logo />
                </a>
                <p class="max-w-xs text-meta text-ink-muted">{{ __('A job board where every application has a timeline you can follow.') }}</p>
            </div>

            <nav aria-labelledby="footer-candidates" class="flex flex-col gap-3 text-sm">
                <h2 id="footer-candidates" class="font-semibold text-ink">{{ __('Find a job') }}</h2>
                <a href="{{ route('jobs.index') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('Browse jobs') }}</a>
                @guest
                    <a href="{{ route('register') }}" class="w-fit text-ink-muted transition-colors hover:text-ink">{{ __('Create a profile') }}</a>
                @endguest
            </nav>

            <nav aria-labelledby="footer-employers" class="flex flex-col gap-3 text-sm">
                <h2 id="footer-employers" class="font-semibold text-ink">{{ __('Hire') }}</h2>
                <a href="{{ route('employers') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('For employers') }}</a>
                @guest
                    <a href="{{ route('register', ['as' => 'employer']) }}" class="w-fit text-ink-muted transition-colors hover:text-ink">{{ __('Post a job') }}</a>
                @endguest
            </nav>

            <nav aria-labelledby="footer-board" class="flex flex-col gap-3 text-sm">
                <h2 id="footer-board" class="font-semibold text-ink">{{ config('app.name') }}</h2>
                <a href="{{ route('about') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('About') }}</a>
                <a href="{{ route('privacy') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('Privacy') }}</a>
                <a href="{{ route('terms') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('Terms') }}</a>
                <a href="{{ route('contact') }}" class="w-fit text-ink-muted transition-colors hover:text-ink" wire:navigate>{{ __('Contact') }}</a>
            </nav>
        </div>

        <div class="border-t border-line">
            <p class="mx-auto max-w-6xl px-6 py-5 text-meta text-ink-muted">&copy; {{ now()->year }} {{ config('app.name') }}</p>
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
    {{-- Livewire only injects its assets into 200 responses, so an error
         page has to load them itself, or Alpine never starts and nothing
         in the navbar opens. --}}
    @if ($errorPage)
        @livewireScripts
    @endif
    @fluxScripts
</body>
</html>
