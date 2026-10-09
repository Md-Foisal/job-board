{{--
    The account menu at the right end of every top bar: the person's photo
    (or initials), then where they can go as themselves. The public navbar
    also lists their spaces here; the company workspace leaves them out,
    since its own switcher already does, and opens Settings inside
    itself.
--}}
@php
    $menuUser = auth()->user();
    $menuAvatar = $menuUser->avatarUrl();
    $withSpaces ??= true;
    $settingsUrl ??= route('profile.edit');
@endphp

<flux:dropdown position="bottom" align="end">
    <button type="button" class="shrink-0 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" aria-label="{{ __('Account menu') }}">
        <flux:avatar size="sm" circle :src="$menuAvatar" :name="$menuUser->name" :initials="$menuUser->initials()" />
    </button>

    <flux:menu class="min-w-60">
        <div class="flex items-center gap-2.5 px-2 py-1.5 text-start text-sm">
            <flux:avatar circle :src="$menuAvatar" :name="$menuUser->name" :initials="$menuUser->initials()" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $menuUser->name }}</flux:heading>
                <flux:text class="truncate">{{ $menuUser->email }}</flux:text>
            </div>
        </div>

        @if ($withSpaces)
            <flux:menu.separator />

            {{-- The only way across from the candidate side to a company
                 workspace, and to start the other side. --}}
            @include('partials.space-menu-items')
        @endif

        <flux:menu.separator />

        @if ($menuUser->isActiveStaff())
            <flux:menu.item :href="filament()->getPanel('admin')->getUrl()" icon="shield-check">
                {{ __('Admin panel') }}
            </flux:menu.item>
        @endif

        @if ($menuUser->isCandidate())
            <flux:menu.item :href="route('candidate.profile.edit')" icon="user" wire:navigate>
                {{ __('My profile') }}
            </flux:menu.item>
        @endif

        <flux:menu.item :href="$settingsUrl" icon="cog-6-tooth" wire:navigate>
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
