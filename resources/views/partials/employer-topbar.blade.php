{{--
    Shell C's top bar. Unlike Shell A's navbar this carries no job search
    and no category browsing: someone reviewing applicants is working, not
    shopping, and public-browse controls here would be an invitation to
    leave the task. What it does carry is the one question Shell A never
    has to answer -- which company am I acting as right now -- and the
    way into the command palette, for the company's applicants and pages.
--}}
<header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-canvas/90 px-4 py-3 backdrop-blur-md sm:gap-4 sm:px-6">
    <div class="flex min-w-0 items-center gap-2 sm:gap-3">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />

        <a href="{{ route('home') }}" class="shrink-0 rounded-control" wire:navigate>
            <x-logo compact />
        </a>

        <flux:separator vertical class="mx-1 h-5" />

        <x-context-switcher :current="$company" />
    </div>

    <div class="hidden flex-1 md:flex">
        @include('partials.command-palette-trigger', ['variant' => 'field', 'label' => __('Search applicants, jobs or pages')])
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <div class="md:hidden">
            @include('partials.command-palette-trigger', ['variant' => 'icon', 'label' => __('Search applicants, jobs or pages')])
        </div>

        <x-theme-toggle />

        @include('partials.account-menu', ['withSpaces' => false])
    </div>
</header>
