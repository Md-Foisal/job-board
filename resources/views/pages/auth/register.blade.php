{{-- The side someone signs up for is asked first, because it decides
     where they land afterwards. The employers' page links here with
     ?as=employer so that choice is already made; an old value from a
     failed submit wins over both. --}}
@php
    $role = old('role', request()->query('as') === 'employer' ? 'employer' : 'candidate');
@endphp

<x-layouts::auth :title="__('Create an account')">
    <x-auth-header :title="__('Create your account')" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
        @csrf

        <flux:radio.group
            name="role"
            :label="__('I want to')"
            :description="__('You can add the other one later, on the same account.')"
            variant="cards"
            class="grid grid-cols-2"
        >
            <flux:radio value="candidate" :checked="$role === 'candidate'">
                <flux:radio.indicator />
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <flux:icon.magnifying-glass class="size-6 icon-sunset" />
                    <flux:heading>{{ __('Find a job') }}</flux:heading>
                    <flux:text size="sm">{{ __('Build a profile and apply') }}</flux:text>
                </div>
            </flux:radio>
            <flux:radio value="employer" :checked="$role === 'employer'">
                <flux:radio.indicator />
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <flux:icon.building-office-2 class="size-6 icon-sunset" />
                    <flux:heading>{{ __('Hire') }}</flux:heading>
                    <flux:text size="sm">{{ __('Post jobs and review applicants') }}</flux:text>
                </div>
            </flux:radio>
        </flux:radio.group>

        <flux:input
            name="name"
            :label="__('Full name')"
            :value="old('name')"
            type="text"
            required
            autocomplete="name"
        />

        <flux:input
            name="email"
            :label="__('Email address')"
            :value="old('email')"
            type="email"
            required
            autocomplete="email"
        />

        <flux:input
            name="password"
            :label="__('Create a password')"
            :description="\App\Support\PasswordPolicy::hint()"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <div class="flex flex-col gap-3">
            <flux:button type="submit" variant="primary" class="btn-sunset w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>

            <p class="text-center text-meta text-ink-muted">
                {{ __('By creating an account, you agree to our') }}
                <a href="{{ route('terms') }}" class="text-sunset-small hover:underline">{{ __('Terms') }}</a>
                {{ __('and') }}
                <a href="{{ route('privacy') }}" class="text-sunset-small hover:underline">{{ __('Privacy Policy') }}</a>.
            </p>
        </div>
    </form>

    <x-slot:footer>
        {{ __('Already have an account?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </x-slot:footer>
</x-layouts::auth>
