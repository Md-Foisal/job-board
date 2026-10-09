<x-layouts::auth :title="__('Reset your password')">
    <x-auth-header
        :title="__('Reset your password')"
        :description="__('Enter the email address you signed up with, and we will send you a link to choose a new password.')"
    />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
        @csrf

        <flux:input
            name="email"
            :label="__('Email address')"
            :value="old('email')"
            type="email"
            required
            autofocus
            autocomplete="email"
        />

        <flux:button variant="primary" type="submit" class="btn-sunset w-full" data-test="email-password-reset-link-button">
            {{ __('Send reset link') }}
        </flux:button>
    </form>

    <x-slot:footer>
        {{ __('Remembered it?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </x-slot:footer>
</x-layouts::auth>
