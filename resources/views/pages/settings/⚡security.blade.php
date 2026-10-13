<?php

use App\Concerns\PasswordValidationRules;
use App\Enums\SignInProvider;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::settings')] #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    /**
     * The company workspace these settings were opened from, if any.
     */
    #[Url(as: 'company')]
    public ?string $workspace = null;

    public string $current_password = '';
    public string $password = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }

    /**
     * Update the password for the currently authenticated user -- or add
     * one, for someone who signed up with Google or GitHub. Adding needs
     * no current password; the page itself is behind a fresh confirmation.
     */
    public function updatePassword(): void
    {
        $user = Auth::user();
        $hadPassword = $user->hasPassword();

        try {
            $validated = $this->validate([
                ...($hadPassword ? ['current_password' => $this->currentPasswordRules()] : []),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password');

            throw $e;
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password');

        Flux::toast(variant: 'success', text: $hadPassword
            ? __('Password updated.')
            : __('Password added. You can sign in with your email address too.'));
    }

    /**
     * Stop signing in with a Google or GitHub account -- unless it is the
     * only way in left, which would lock the person out.
     */
    public function disconnect(string $provider): void
    {
        $provider = SignInProvider::tryFrom($provider) ?? abort(404);
        $user = Auth::user();

        if (! $user->canDisconnect($provider)) {
            Flux::toast(variant: 'danger', text: __('Add a password first, so you can still sign in.'));

            return;
        }

        $user->connectedAccounts()->where('provider', $provider->value)->delete();

        Flux::toast(variant: 'success', text: __(':provider disconnected.', ['provider' => $provider->label()]));
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        // Staff must keep two-factor on: the admin panel refuses them
        // without it, so switching it off would only lock them out.
        abort_if(auth()->user()->isStaff(), 403);

        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<x-page width="narrow">
    @include('partials.settings-heading')

    <x-pages::settings.layout current="security" :workspace="$workspace">
        @php
            $user = auth()->user();
            $connected = $user->connectedAccounts()->get()->keyBy(fn ($account) => $account->provider->value);
        @endphp

        <x-card as="form" method="POST" wire:submit="updatePassword" class="space-y-6">
            <flux:heading size="lg" level="2">{{ __('Password') }}</flux:heading>

            @if ($user->hasPassword())
                <flux:input
                    wire:model="current_password"
                    :label="__('Current password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
            @else
                <flux:text>
                    {{ __('You sign in with :providers. Add a password to sign in with your email address as well.', [
                        'providers' => $connected->map(fn ($account) => $account->provider->label())->join(' or '),
                    ]) }}
                </flux:text>
            @endif
            <flux:input
                wire:model="password"
                :label="$user->hasPassword() ? __('New password') : __('Password')"
                :description="\App\Support\PasswordPolicy::hint()"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-password-button">
                    {{ $user->hasPassword() ? __('Save') : __('Add password') }}
                </flux:button>
            </div>
        </x-card>

        {{-- A provider shows when it can be used here, or when it is
             already connected (so it can still be disconnected). Connect is
             a plain form: it leaves the site for the provider and comes
             back through SocialSignInController. --}}
        @php
            $providers = collect(\App\Enums\SignInProvider::cases())
                ->filter(fn ($provider) => $provider->isConfigured() || $connected->has($provider->value));
        @endphp

        @if ($providers->isNotEmpty())
            <x-card as="section" aria-labelledby="connected-accounts-heading" class="flex flex-col gap-4 text-sm">
                <div>
                    <flux:heading size="lg" level="2" id="connected-accounts-heading">{{ __('Connected accounts') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Sign in with one of these instead of typing a password.') }}</flux:text>
                </div>

                <ul class="divide-y divide-line">
                    @foreach ($providers as $provider)
                        @php
                            $account = $connected->get($provider->value);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" data-test="connected-{{ $provider->value }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-provider-logo :provider="$provider" />
                                <div class="min-w-0">
                                    <div class="font-medium text-ink">{{ $provider->label() }}</div>
                                    <div class="truncate text-ink-muted">{{ $account ? ($account->email ?? __('Connected')) : __('Not connected') }}</div>
                                </div>
                            </div>

                            @if ($account && $user->canDisconnect($provider))
                                <flux:button size="sm" wire:click="disconnect('{{ $provider->value }}')">{{ __('Disconnect') }}</flux:button>
                            @elseif ($account)
                                <flux:text size="sm">{{ __('Your only way to sign in. Add a password to disconnect it.') }}</flux:text>
                            @elseif ($provider->isConfigured())
                                <form method="POST" action="{{ route('social.connect', $provider) }}">
                                    @csrf
                                    <flux:button size="sm" type="submit">{{ __('Connect') }}</flux:button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if ($canManageTwoFactor)
            <x-card as="section" aria-labelledby="two-factor-heading" class="flex flex-col gap-4 text-sm" wire:cloak>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <flux:heading size="lg" level="2" id="two-factor-heading">{{ __('Two-factor authentication') }}</flux:heading>
                    @if ($twoFactorEnabled)
                        <flux:badge color="green" size="sm">{{ __('On') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('Off') }}</flux:badge>
                    @endif
                </div>

                @if (auth()->user()->isStaff() && ! $twoFactorEnabled)
                    <flux:callout variant="warning" icon="shield-exclamation">
                        <flux:callout.heading>{{ __('Required for staff accounts') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Your account can take moderation actions, so the admin panel stays closed until two-factor authentication is set up.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if ($twoFactorEnabled)
                    <div class="space-y-4">
                        <flux:text>
                            {{ __('When you sign in, we also ask for a code from the authenticator app on your phone.') }}
                        </flux:text>

                        @if (auth()->user()->isStaff())
                            <flux:text>
                                {{ __('Two-factor authentication cannot be switched off on a staff account.') }}
                            </flux:text>
                        @else
                            <div class="flex justify-start">
                                <flux:button
                                    variant="danger"
                                    wire:click="disable"
                                >
                                    {{ __('Turn off two-factor') }}
                                </flux:button>
                            </div>
                        @endif

                        <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                    </div>
                @else
                    <div class="space-y-4">
                        <flux:text>
                            {{ __('Add a second step to signing in: a code from an authenticator app on your phone, such as Google Authenticator or 1Password. Someone who learns your password still cannot get in.') }}
                        </flux:text>

                        <flux:modal.trigger name="two-factor-setup-modal">
                            <flux:button
                                variant="primary"
                                wire:click="$dispatch('start-two-factor-setup')"
                            >
                                {{ __('Set up two-factor') }}
                            </flux:button>
                        </flux:modal.trigger>

                        <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                    </div>
                @endif
            </x-card>
        @endif
    </x-pages::settings.layout>
</x-page>
