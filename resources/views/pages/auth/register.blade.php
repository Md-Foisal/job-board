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

        <x-role-choice :selected="$role" />

        <x-social-sign-in in-form />

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
