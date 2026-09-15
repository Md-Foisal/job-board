{{--
    Shell C's top bar. Unlike Shell A's navbar this carries no job search
    and no category browsing: someone reviewing applicants is working, not
    shopping, and public-browse controls here would be an invitation to
    leave the task. What it does carry is the one question Shell A never
    has to answer -- which company am I acting as right now.
--}}
<header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-zinc-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-950">
    <div class="flex min-w-0 shrink items-center gap-3">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />

        <a href="{{ route('home') }}" class="shrink-0 font-display text-lg font-bold text-brand-700 dark:text-brand-400" wire:navigate>
            JobBoard
        </a>

        <flux:separator vertical class="mx-1 h-5" />

        <x-context-switcher :current="$company" />
    </div>

    <div class="flex shrink-0 items-center gap-4">
        <x-theme-toggle />

        <flux:dropdown position="bottom" align="end">
            <button type="button" class="cursor-pointer" aria-label="{{ __('Account menu') }}">
                <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
            </button>

            <flux:menu>
                <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
                    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                    <div class="grid flex-1 text-start text-sm leading-tight">
                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                    </div>
                </div>

                <flux:menu.separator />

                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </div>
</header>
