@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        @include('partials.svg-defs')

        <div class="relative isolate flex min-h-svh flex-col items-center justify-center px-4 py-10 sm:px-6">
            {{-- The brand stays in the background: the faint dot grid of
                 the home hero and a soft Sunset glow above the card. Nothing
                 in it moves or can be clicked, so it never competes with the
                 form for attention. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,var(--color-line-strong)_1px,transparent_0)] [background-size:22px_22px] [mask-image:radial-gradient(ellipse_at_top,black_15%,transparent_70%)]"></div>
                <div class="bg-sunset absolute -top-48 left-1/2 h-96 w-[40rem] -translate-x-1/2 rounded-full opacity-20 blur-3xl"></div>
            </div>

            <div class="flex w-full max-w-md flex-col items-center gap-6">
                {{-- The only way out of this page, and deliberately the
                     only one: an auth screen carries no site navigation
                     (every extra link is an exit from a flow the visitor
                     started), but the brand always links home -- the
                     convention people actually rely on. It is the visible
                     logo with its wordmark rather than a bare icon so that
                     it reads as a link, and it is the guest navbar's
                     logo, larger. --}}
                <a href="{{ route('home') }}" class="rounded-control" wire:navigate>
                    <x-logo size="lg" />
                </a>

                <x-card padding="lg" class="w-full shadow-lift">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </x-card>

                {{-- The other way in ("New here? Create an account"), under
                     the card rather than inside it, so the card holds one
                     task. --}}
                @isset($footer)
                    <p class="text-center text-sm text-ink-muted">{{ $footer }}</p>
                @endisset
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
