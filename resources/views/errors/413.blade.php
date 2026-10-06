<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => __('Upload too large')])
</head>

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    <header class="flex items-center justify-between px-6 py-4">
        <a href="{{ route('home') }}" class="rounded-control">
            <x-logo />
        </a>
        <x-theme-toggle />
    </header>

    <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
        <p class="font-display text-7xl font-bold text-sunset">413</p>
        <flux:heading size="xl" level="1" class="mt-4">{{ __('That upload was too large') }}</flux:heading>
        <flux:subheading size="lg" class="mt-2 max-w-md">
            {{ __('Nothing was saved. Go back and choose a smaller file — the size limit is shown next to each upload field.') }}
        </flux:subheading>

        {{-- The form is cut off before any session starts, so this page
             cannot send anyone back with a message; the browser's own
             referrer is the one thing that still knows where they were. --}}
        <a
            href="{{ url()->previous() }}"
            class="btn-sunset mt-8 rounded-control px-5 py-2.5 text-sm font-medium"
        >
            {{ __('Back to the form') }}
        </a>
    </main>

    {{-- Livewire only injects its assets into 200 responses, so an error
         page has to load them itself, or Alpine never starts and the
         theme switch does nothing. --}}
    @livewireScripts
    @fluxScripts
</body>

</html>
