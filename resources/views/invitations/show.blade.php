<x-layouts::auth :title="__('Invitation')">
    <div class="flex flex-col gap-6 text-center">
        <div class="flex flex-col gap-2">
            <flux:heading size="lg" class="font-display">
                {{ __('Join :company', ['company' => $invitation->company->name]) }}
            </flux:heading>
            <flux:text>
                {{ __(':inviter invited :email to join as :role.', [
                    'inviter' => $invitation->invitedBy?->name ?? $invitation->company->name,
                    'email' => $invitation->email,
                    'role' => \Illuminate\Support\Str::lower($invitation->role->label()),
                ]) }}
            </flux:text>
        </div>

        @guest
            {{-- Most people following an invitation have never used the
                 site: telling them only to "sign in" leaves the commonest
                 case unaddressed. --}}
            <flux:text>{{ __('Sign in with that address to accept, or create an account with it.') }}</flux:text>

            <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
                @csrf
                <flux:button variant="primary" type="submit" class="w-full">{{ __('Continue') }}</flux:button>
            </form>
        @endguest

        @auth
            @if ($addressMatches)
                <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
                    @csrf
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Accept invitation') }}
                    </flux:button>
                </form>
            @else
                {{-- Naming both addresses is the difference between a dead
                     end and an obvious next step. --}}
                <flux:callout variant="warning">
                    {{ __('This invitation was sent to :invited, but you are signed in as :current.', [
                        'invited' => $invitation->email,
                        'current' => auth()->user()->email,
                    ]) }}
                </flux:callout>

                {{-- Drawn as a real control, not subtle text: it is the
                     only way forward on this screen, and it carries the
                     invitation through the sign-in rather than dropping
                     the person on the homepage to find the email again. --}}
                <form method="POST" action="{{ route('invitations.switch-account', $invitation->token) }}">
                    @csrf
                    <flux:button variant="filled" type="submit" class="w-full">
                        {{ __('Sign in as :email', ['email' => $invitation->email]) }}
                    </flux:button>
                </form>
            @endif
        @endauth
    </div>
</x-layouts::auth>
