<x-layouts::auth :title="__('Choose a new password')">
    <x-auth-header :title="__('Choose a new password')" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <flux:input
            name="email"
            :value="old('email', request('email'))"
            :label="__('Email address')"
            type="email"
            required
            autocomplete="email"
        />

        <flux:input
            name="password"
            :label="__('New password')"
            :description="\App\Support\PasswordPolicy::hint()"
            type="password"
            required
            autofocus
            autocomplete="new-password"
            viewable
        />

        <flux:button type="submit" variant="primary" class="btn-sunset w-full" data-test="reset-password-button">
            {{ __('Save new password') }}
        </flux:button>
    </form>
</x-layouts::auth>
