<?php

use App\Concerns\ProfileValidationRules;
use App\Support\LocalTime;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Account settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Empty means automatic: follow the browser.
     */
    public string $timezone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->timezone = Auth::user()->timezone_automatic ? '' : (string) Auth::user()->timezone;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * A zone picked by hand stays put; going back to automatic takes the
     * browser's zone straight away rather than on the next page.
     */
    public function updateTimezone(): void
    {
        $validated = $this->validate([
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ], attributes: ['timezone' => __('time zone')]);

        $user = Auth::user();
        $automatic = blank($validated['timezone']);

        $user->timezone_automatic = $automatic;
        $user->timezone = $automatic ? (LocalTime::fromBrowser() ?? $user->timezone) : $validated['timezone'];
        $user->save();

        Flux::toast(variant: 'success', text: __('Time zone updated.'));
    }

    /**
     * What "Automatic" means for this person right now, to show beside it.
     */
    #[Computed]
    public function detectedTimezone(): ?string
    {
        return LocalTime::fromBrowser() ?? (Auth::user()->timezone_automatic ? Auth::user()->timezone : null);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<x-page width="narrow">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Account settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Account')">
        <x-card as="form" wire:submit="updateProfileInformation" class="mt-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </x-card>

        <x-card as="form" wire:submit="updateTimezone" class="mt-6 w-full space-y-6">
            <flux:select
                wire:model="timezone"
                :label="__('Time zone')"
                :description="__('Dates and times across the site, and in our emails, are shown in this zone. Automatic follows the device you are using, including when you travel.')"
            >
                <flux:select.option value="">
                    {{ $this->detectedTimezone ? __('Automatic — :zone', ['zone' => LocalTime::label($this->detectedTimezone)]) : __('Automatic') }}
                </flux:select.option>
                @foreach (LocalTime::choices() as $region => $zones)
                    <optgroup label="{{ $region }}">
                        @foreach ($zones as $zone => $label)
                            <flux:select.option :value="$zone">{{ $label }}</flux:select.option>
                        @endforeach
                    </optgroup>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-timezone-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </x-card>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</x-page>
