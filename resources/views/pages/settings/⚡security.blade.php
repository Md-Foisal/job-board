<?php

use App\Concerns\PasswordValidationRules;
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
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password');

        Flux::toast(variant: 'success', text: __('Password updated.'));
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
        <x-card as="form" method="POST" wire:submit="updatePassword" class="space-y-6">
            <flux:heading size="lg" level="2">{{ __('Password') }}</flux:heading>

            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                :description="\App\Support\PasswordPolicy::hint()"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </x-card>

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
                            {{ __('When you sign in, we ask for a code from the authenticator app on your phone as well as your password.') }}
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
