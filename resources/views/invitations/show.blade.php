{{--
    An invitation to join a company, opened from the email's link. It
    sits in the site's own frame, as GitHub's organisation invitations
    do, rather than on a bare sign-in page: most people who open it have
    never used the site, and the navbar and footer say where they are.

    The card says who is asking, for what role and what that role opens,
    and until when -- then the one way forward for whoever is reading.
--}}
@php
    $company = $invitation->company;
    $inviter = $invitation->invitedBy;
@endphp

<x-layouts::guest :title="__('Join :company', ['company' => $company->name])">
    <div class="mx-auto flex max-w-lg flex-col px-4 py-12 sm:py-20">
        <x-card class="flex flex-col items-center gap-6 text-center">
            <x-company-logo :company="$company" size="lg" />

            <div>
                <h1 class="font-display text-title text-balance text-ink">{{ __('Join :company', ['company' => $company->name]) }}</h1>

                <p class="mt-3 flex items-center justify-center gap-2 text-ink-soft">
                    @if ($inviter)
                        <flux:avatar circle size="xs" :src="$inviter->avatarUrl()" :name="$inviter->name" :initials="$inviter->initials()" class="shrink-0" />
                    @endif
                    <span class="min-w-0 text-balance">
                        {{ __(':inviter invited :email.', [
                            'inviter' => $inviter?->name ?? $company->name,
                            'email' => $invitation->email,
                        ]) }}
                    </span>
                </p>
            </div>

            <div class="w-full rounded-control bg-surface p-4 text-start ring-1 ring-line">
                <p class="text-sm font-medium text-ink">{{ __('Role: :role', ['role' => __($invitation->role->label())]) }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ $invitation->role->description() }}</p>
                <p class="mt-3 text-xs text-ink-muted">
                    {{ __('The invitation is open until :date.', ['date' => \App\Support\LocalTime::of($invitation->expires_at)->format(\App\Support\DateFormat::DAY)]) }}
                </p>
            </div>

            <div class="flex w-full flex-col gap-4">
                @guest
                    {{-- Most people following an invitation have never used the
                         site: telling them only to "sign in" leaves the commonest
                         case unaddressed. --}}
                    <flux:text>{{ __('Sign in with that address to accept, or create an account with it.') }}</flux:text>

                    <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
                        @csrf
                        <flux:button variant="primary" type="submit" class="btn-sunset w-full">{{ __('Continue') }}</flux:button>
                    </form>
                @endguest

                @auth
                    @if ($addressMatches)
                        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
                            @csrf
                            <flux:button variant="primary" type="submit" class="btn-sunset w-full">
                                {{ __('Accept invitation') }}
                            </flux:button>
                        </form>
                    @else
                        {{-- Naming both addresses is the difference between a dead
                             end and an obvious next step. --}}
                        <flux:callout variant="warning" class="text-start">
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
        </x-card>
    </div>
</x-layouts::guest>
