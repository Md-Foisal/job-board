<x-layouts::auth :title="__('Confirm it’s you')">
    <x-auth-header
        :title="__('Confirm it’s you')"
        :description="__('For your security, enter your password again before you change how you sign in.')"
    />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
        @csrf

        <div class="relative">
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autofocus
                autocomplete="current-password"
                viewable
            />

            @if (Route::has('password.request'))
                <flux:link class="absolute top-0 end-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot password?') }}
                </flux:link>
            @endif
        </div>

        <flux:button variant="primary" type="submit" class="btn-sunset w-full" data-test="confirm-password-button">
            {{ __('Continue') }}
        </flux:button>
    </form>
</x-layouts::auth>
