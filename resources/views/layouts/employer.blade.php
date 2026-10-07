{{--
    Shell C -- the company workspace.

    Every page inside it is about one company, and that company arrives as
    a required prop rather than being read from the session, so the layout
    can never render for a different company than the URL asked for.

    The sidebar's entries live in App\Support\Navigation, which the
    command palette reads too. Applications review is intentionally
    absent from them: it belongs to one job posting, not to the company,
    so it is reached from the job listing rather than from a permanent
    nav entry.
--}}
@props(['company' => null, 'title' => null])

{{-- Blade pages hand the company in explicitly, which keeps the dependency
     visible at the call site. A Livewire full-page component cannot: its
     #[Layout] attribute only takes constants. Falling back to the route
     parameter costs nothing in correctness -- every page in this shell is
     behind the {company:slug} prefix and the membership guard, so the URL
     is already the authority on which company this is -- and the layout
     only renders on the first load, never on a Livewire update. --}}
@php
    // Block form on purpose: the inline @php(...) directive compiles to an
    // unterminated <?php( ... ) when this layout is rendered through
    // Livewire's #[Layout] path, which then swallows the markup after it.
    $company ??= request()->route('company');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    @include('partials.svg-defs')
    @include('partials.employer-topbar', ['company' => $company])

    <div class="flex flex-1">
        <flux:sidebar collapsible sticky
            class="border-e border-line bg-surface">
            <flux:sidebar.header class="justify-end">
                <flux:sidebar.collapse :tooltip="__('Collapse sidebar')" />
            </flux:sidebar.header>

            {{-- The one thing an employer comes to do most, kept in reach
                 from every page of the workspace, as large boards do; only
                 for those whose role lets them post. --}}
            @if ($postJob = \App\Support\Navigation\Navigation::postJob(auth()->user(), $company))
                <flux:button :href="$postJob->url" variant="primary" :icon="$postJob->icon" class="w-full in-data-flux-sidebar-collapsed-desktop:hidden" wire:navigate>
                    {{ $postJob->label }}
                </flux:button>
                <div class="hidden in-data-flux-sidebar-collapsed-desktop:block">
                    <flux:sidebar.item :icon="$postJob->icon" :href="$postJob->url" wire:navigate>
                        {{ $postJob->label }}
                    </flux:sidebar.item>
                </div>
            @endif

            {{-- Daily work first, company housekeeping after it; the
                 entries come from App\Support\Navigation, which the
                 command palette searches too. --}}
            <flux:sidebar.nav>
                @include('partials.sidebar-sections', ['sections' => \App\Support\Navigation\Navigation::company(auth()->user(), $company)])
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-w-0 flex-1 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    <livewire:command-palette :company="$company" />

    @include('partials.flash-toasts')

    @persist('toast')
    <flux:toast.group position="bottom end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
