<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => __('Access denied')])
</head>

<body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="flex items-center justify-between px-6 py-4">
        <a href="{{ route('home') }}" class="font-display text-lg font-bold text-brand-700 dark:text-brand-400">
            JobBoard
        </a>
        <x-theme-toggle />
    </header>

    <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
        <p class="font-display text-7xl font-bold text-brand-700 dark:text-brand-400">403</p>
        <flux:heading size="xl" level="1" class="mt-4">{{ __("You don't have access to this page") }}</flux:heading>
        <flux:subheading size="lg" class="mt-2 max-w-md">
            {{ filled($exception->getMessage() ?? null)
                ? $exception->getMessage()
                : __('This area is restricted, or something you tried needs a different account.') }}
        </flux:subheading>

        <a
            href="{{ route('home') }}"
            class="mt-8 rounded-md bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700"
        >
            {{ __('Back to homepage') }}
        </a>
    </main>

    @fluxScripts
</body>

</html>
