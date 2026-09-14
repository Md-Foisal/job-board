<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <nav class="flex items-center justify-between gap-4 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <a href="{{ route('home') }}" class="shrink-0 font-display text-lg font-bold text-brand-700 dark:text-brand-400">
            JobBoard
        </a>

        <div class="hidden flex-1 items-center gap-2 md:flex">
            {{-- The homepage carries its own large hero search right below
                 this nav, so repeating it here would just be noise --}}
            @unless (request()->routeIs('home'))
                <livewire:job-search-autocomplete variant="compact" />
            @endunless

            <flux:dropdown>
                <flux:button variant="ghost" icon:trailing="chevron-down">Categories</flux:button>
                <flux:menu>
                    @foreach ($navCategories as $navCategory)
                        <flux:menu.item href="{{ route('categories.show', $navCategory) }}" wire:navigate>
                            {{ $navCategory->name }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        </div>

        <div class="flex shrink-0 items-center gap-4">
            <x-theme-toggle />

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

    {{-- Plain (non-Livewire) controller redirects flash session('success'/'error')
         data. Flux::toast() only reaches a <flux:toast> from inside a Livewire
         request, which a plain controller redirect can't do -- but Flux also
         ships a standalone JS API (window.Flux.toast) that works anywhere once
         Alpine has booted, so a flashed message here just calls that instead of
         a bespoke banner (same fix as layouts/app/sidebar.blade.php). --}}
    @if (session('success'))
        <script>
            // alpine:init fires the instant Alpine.start() begins -- BEFORE
            // Alpine has walked the DOM and wired up the toast host
            // component's "toast-show" listener. Calling Flux.toast()
            // synchronously here dispatches the event into the void because
            // nothing is listening yet. Queuing the call with setTimeout
            // pushes it to the next tick, by which point Alpine's
            // (synchronous) DOM walk has finished and the listener exists.
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

    <main>
        {{ $slot }}
    </main>

    @persist('toast')
    <flux:toast.group position="top end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    <footer class="mt-16 border-t border-zinc-200 px-6 py-8 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
        <p>&copy; {{ now()->year }} JobBoard.</p>
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
