@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                {{-- The only way out of this page, and deliberately the
                     only one: an auth screen carries no site navigation
                     (every extra link is an exit from a flow the visitor
                     started), but the brand always links home -- the
                     convention people actually rely on. It is the visible
                     wordmark rather than a bare icon so that it reads as
                     a link, and it matches the guest navbar's brand
                     exactly. --}}
                <a
                    href="{{ route('home') }}"
                    class="mb-2 flex flex-col items-center font-display text-2xl font-bold text-brand-700 dark:text-brand-400"
                    wire:navigate
                >
                    {{ config('app.name') }}
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
