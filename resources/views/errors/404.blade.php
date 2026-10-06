<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => __('Page not found')])
</head>

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    <header class="flex items-center justify-between px-6 py-4">
        <a href="{{ route('home') }}" class="rounded-control">
            <x-logo />
        </a>
        <x-theme-toggle />
    </header>

    <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
        <p class="font-display text-7xl font-bold text-sunset">404</p>
        <flux:heading size="xl" level="1" class="mt-4">{{ __("This page doesn't exist") }}</flux:heading>
        <flux:subheading size="lg" class="mt-2 max-w-md">
            {{ __("The link may be broken, or the listing might have been taken down.") }}
        </flux:subheading>

        <a
            href="{{ route('home') }}"
            class="btn-sunset mt-8 rounded-control px-5 py-2.5 text-sm font-medium"
        >
            {{ __('Back to homepage') }}
        </a>
    </main>

    {{-- Livewire only injects its assets into 200 responses, so an error
         page has to load them itself, or Alpine never starts and the
         theme switch does nothing. --}}
    @livewireScripts
    @fluxScripts
</body>

</html>
