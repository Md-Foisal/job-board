{{-- An anonymous component only receives passed data as variables when it
     declares them, so without this the :title layouts/app.blade.php passes
     in never reached partials.head and every page fell back to the bare
     app name in the browser tab. --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    @include('partials.svg-defs')
    @include('partials.navbar', ['showSidebarToggle' => true])

    <div class="flex flex-1">
        {{-- Grouped by the two jobs a candidate comes here for: looking for
             work, and keeping the profile that applications are made from.
             The entries come from App\Support\Navigation, which the
             command palette searches too. It can be narrowed to icons on a
             desktop, and the browser remembers the choice, but it opens
             wide: the labels are what make it quick to scan. --}}
        <flux:sidebar collapsible sticky
            class="border-e border-line bg-surface">
            <flux:sidebar.header class="justify-end">
                <flux:sidebar.collapse :tooltip="__('Collapse sidebar')" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @auth
                    @include('partials.sidebar-sections', ['sections' => \App\Support\Navigation\Navigation::personal(auth()->user())])
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-w-0 flex-1 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @auth
        <livewire:command-palette />
    @endauth

    @include('partials.flash-toasts')

    @persist('toast')
    <flux:toast.group position="bottom end">
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
