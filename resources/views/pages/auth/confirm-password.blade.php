{{-- Someone who signed up with Google or GitHub and never added a
     password confirms it is them by signing in with that account again
     (SocialSignInController::confirm). --}}
@php
    $user = auth()->user();
    $connected = $user->hasPassword() ? collect() : $user->connectedAccounts()->get();
@endphp

<x-layouts::auth :title="__('Confirm it’s you')">
    <x-auth-header
        :title="__('Confirm it’s you')"
        :description="$user->hasPassword()
            ? __('For your security, enter your password again before you change how you sign in.')
            : __('For your security, sign in again before you change how you sign in.')"
    />

    <x-auth-session-status class="text-center" :status="session('status')" />

    @error('social')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
    @enderror

    @if ($user->hasPassword())
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
    @else
        <div class="flex flex-col gap-3">
            @foreach ($connected as $account)
                @if ($account->provider->isConfigured())
                    <form method="POST" action="{{ route('social.confirm', $account->provider) }}">
                        @csrf
                        <x-social-button :provider="$account->provider" type="submit" data-test="confirm-with-{{ $account->provider->value }}">
                            {{ __('Continue with :provider', ['provider' => $account->provider->label()]) }}
                        </x-social-button>
                    </form>
                @endif
            @endforeach
        </div>
    @endif
</x-layouts::auth>
