<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <nav class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <a href="{{ route('home') }}" class="font-display text-lg font-bold text-brand-700 dark:text-brand-400">
            JobBoard
        </a>

        <div class="flex items-center gap-4">
            @guest
                <a href="{{ route('register') }}" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">For Employers</a>
                <a href="{{ route('login') }}" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">Log in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Sign up
                </a>
            @endguest

            @auth
                <a href="{{ route('dashboard') }}" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-zinc-600 hover:text-brand-700 dark:text-zinc-400 dark:hover:text-brand-400">Log out</button>
                </form>
            @endauth
        </div>
    </nav>

    @if (session('success'))
        <div class="mx-auto mt-4 max-w-4xl px-4">
            <div class="rounded-md border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-700 dark:bg-success-950 dark:text-success-300">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mx-auto mt-4 max-w-4xl px-4">
            <div class="rounded-md border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-300">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-zinc-200 px-6 py-8 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
        <p>&copy; {{ now()->year }} JobBoard.</p>
    </footer>
</body>
</html>
