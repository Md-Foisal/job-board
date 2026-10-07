{{--
    One frame for every error page, so they read as part of the site and
    say the same things in the same order: what happened, what to do
    about it, then the code in small print (USWDS 404 template: the code
    is for support, not the headline).

    "site" keeps the navbar and footer, for errors that happen inside a
    normal request (403, 404, 419, 429), so the way on is the site's own
    navigation. "bare" is the logo and the theme switch only, for errors
    where the session or the database may not be there to draw a navbar
    (413 is refused before a session starts; 500 and 503 are the app
    itself failing).

    The slot holds the actions; the first one is the main way on. Under
    them, the contact page, for when none of them helps -- except while
    the site is down for maintenance, when the form could not be sent.
--}}
@props([
    'code',
    'heading',
    'message',
    'icon' => 'exclamation-triangle',
    'shell' => 'site',
    'contact' => true,
])

@if ($shell === 'site')
    <x-layouts::guest :title="$heading" error-page>
        <section class="mx-auto flex max-w-xl flex-col items-center px-6 py-20 text-center sm:py-28">
            <x-icon-tile :icon="$icon" />
            <h1 class="mt-6 text-balance text-heading text-ink sm:text-title">{{ $heading }}</h1>
            <p class="mt-3 max-w-md text-body text-ink-muted">{{ $message }}</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
            @if ($contact)
                <p class="mt-6 text-sm text-ink-muted">
                    {{ __('Still stuck?') }}
                    <a href="{{ route('contact') }}" class="font-medium text-sunset-small hover:underline">{{ __('Contact us') }}</a>
                </p>
            @endif
            <p class="mt-10 font-mono text-meta text-ink-muted">{{ __('Error :code', ['code' => $code]) }}</p>
        </section>
    </x-layouts::guest>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $heading])
    </head>
    <body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
        @include('partials.svg-defs')

        <header class="flex items-center justify-between border-b border-line px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="rounded-control">
                <x-logo />
            </a>
            <x-theme-toggle />
        </header>

        <main class="flex flex-1 flex-col items-center justify-center px-6 py-20 text-center">
            <x-icon-tile :icon="$icon" />
            <h1 class="mt-6 max-w-xl text-balance text-heading text-ink sm:text-title">{{ $heading }}</h1>
            <p class="mt-3 max-w-md text-body text-ink-muted">{{ $message }}</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
            @if ($contact)
                <p class="mt-6 text-sm text-ink-muted">
                    {{ __('Still stuck?') }}
                    <a href="{{ route('contact') }}" class="font-medium text-sunset-small hover:underline">{{ __('Contact us') }}</a>
                </p>
            @endif
            <p class="mt-10 font-mono text-meta text-ink-muted">{{ __('Error :code', ['code' => $code]) }}</p>
        </main>

        {{-- Livewire only injects its assets into 200 responses, so an error
             page has to load them itself, or Alpine never starts and the
             theme switch does nothing. --}}
        @livewireScripts
        @fluxScripts
    </body>
    </html>
@endif
