<x-layouts::auth :title="__('Log in')">
    <x-auth-header :title="__('Log in to :app', ['app' => config('app.name')])" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
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

        <div class="relative">
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            @if (Route::has('password.request'))
                <flux:link class="absolute top-0 end-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot password?') }}
                </flux:link>
            @endif
        </div>

        <flux:checkbox name="remember" :label="__('Keep me logged in')" :checked="old('remember')" />

        <flux:button variant="primary" type="submit" class="btn-sunset w-full" data-test="login-button">
            {{ __('Log in') }}
        </flux:button>
    </form>

    @if (Route::has('register'))
        <x-slot:footer>
            {{ __('New to :app?', ['app' => config('app.name')]) }}
            <flux:link :href="route('register')" wire:navigate>{{ __('Create an account') }}</flux:link>
        </x-slot:footer>
    @endif
</x-layouts::auth>
