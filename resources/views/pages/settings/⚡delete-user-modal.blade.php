<?php

use App\Concerns\PasswordValidationRules;
use App\Filament\Support\ConfirmsPassword;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $user = Auth::user();

        // Without a password, a fresh sign-in with Google or GitHub is the
        // confirmation (SocialSignInController::confirm), within the same
        // window a typed password opens.
        if ($user->hasPassword()) {
            $this->validate([
                'password' => $this->currentPasswordRules(),
            ]);
        } elseif (! ConfirmsPassword::recentlyConfirmed()) {
            $this->addError('password', __('Confirm it is you first.'));

            return;
        }

        // Account-deletion guard: don't let an employer's account vanish
        // while a job posting of theirs is still open -- any candidate who
        // has applied (or might still apply) would be left stranded with
        // no way to know the job disappeared.
        // Open rather than publicly live: a posting hidden while reports
        // are reviewed can still have applicants waiting on it.
        if ($user->jobPostings()
            ->where('availability_status', \App\Enums\AvailabilityStatus::Active)
            ->where('expires_at', '>', now())
            ->exists()) {
            $this->addError('password', 'You have an active job posting. Please wait for it to close or expire before deleting your account.');
            return;
        }

        // Nor may the last owner walk out on a team: the others would be
        // left with a company nobody can run -- the rule the team page
        // already applies to removing them (Membership::isLastActiveOwner).
        // Someone alone in their company can go; there is no one to hand
        // it to.
        $stranded = $user->memberships()
            ->where('status', \App\Enums\MembershipStatus::Active)
            ->with('company')
            ->get()
            ->first(fn ($membership) => $membership->isLastActiveOwner()
                && $membership->company->memberships()
                    ->where('status', \App\Enums\MembershipStatus::Active)
                    ->whereKeyNot($membership->id)
                    ->exists());

        if ($stranded) {
            $this->addError('password', __('You are the only owner of :company. Make someone else an owner from its team page before deleting your account.', [
                'company' => $stranded->company->name,
            ]));

            return;
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                {{ __('Your account is switched off straight away. Sign in again within :days days to restore it. After that your name, email, profile and files are erased for good; applications you sent stay with employers only as anonymous records.', ['days' => \App\Models\User::DELETION_GRACE_DAYS]) }}
            </flux:subheading>
        </div>

        @php
            $user = auth()->user();
            $needsSignIn = ! $user->hasPassword() && ! \App\Filament\Support\ConfirmsPassword::recentlyConfirmed();
        @endphp

        @if ($user->hasPassword())
            <flux:input wire:model="password" :label="__('Enter your password to confirm')" type="password" viewable />
        @elseif ($needsSignIn)
            {{-- Leaves the page for the provider and comes back here, inside
                 the confirmation window; the button below then works. --}}
            <div class="flex flex-col gap-3">
                <flux:text>{{ __('Sign in again to confirm it is you.') }}</flux:text>
                @foreach ($user->connectedAccounts()->get() as $account)
                    @if ($account->provider->isConfigured())
                        <x-social-button
                            :provider="$account->provider"
                            type="submit"
                            form="confirm-with-{{ $account->provider->value }}"
                        >
                            {{ __('Continue with :provider', ['provider' => $account->provider->label()]) }}
                        </x-social-button>
                    @endif
                @endforeach
            </div>
            <flux:error name="password" />
        @else
            <flux:error name="password" />
        @endif

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" :disabled="$needsSignIn" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>

    {{-- Outside the Livewire form, which they cannot sit inside: the
         buttons above reach them through their form attribute. --}}
    @if ($needsSignIn)
        @foreach (auth()->user()->connectedAccounts()->get() as $account)
            <form id="confirm-with-{{ $account->provider->value }}" method="POST" action="{{ route('social.confirm', $account->provider) }}" class="hidden">
                @csrf
            </form>
        @endforeach
    @endif
</flux:modal>
