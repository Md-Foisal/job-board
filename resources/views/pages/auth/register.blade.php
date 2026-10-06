{{-- The side someone signs up for is asked first, because it decides
     where they land afterwards. The employers' page links here with
     ?as=employer so that choice is already made; an old value from a
     failed submit wins over both. --}}
@php
    $role = old('role', request()->query('as') === 'employer' ? 'employer' : 'candidate');
@endphp

<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create your account')" :description="__('You can add the other side to the same account later.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:radio.group name="role" :label="__('I want to')" variant="cards" class="grid grid-cols-2">
                <flux:radio value="candidate" :checked="$role === 'candidate'">
                    <flux:radio.indicator />
                    <div class="flex flex-col items-center gap-1.5 text-center">
                        <flux:icon.magnifying-glass class="size-6 text-ink" />
                        <flux:heading>{{ __('Find a job') }}</flux:heading>
                        <flux:text size="sm">{{ __('Build a profile and apply') }}</flux:text>
                    </div>
                </flux:radio>
                <flux:radio value="employer" :checked="$role === 'employer'">
                    <flux:radio.indicator />
                    <div class="flex flex-col items-center gap-1.5 text-center">
                        <flux:icon.building-office-2 class="size-6 text-ink" />
                        <flux:heading>{{ __('Hire') }}</flux:heading>
                        <flux:text size="sm">{{ __('Post jobs and review applicants') }}</flux:text>
                    </div>
                </flux:radio>
            </flux:radio.group>

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />


            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
