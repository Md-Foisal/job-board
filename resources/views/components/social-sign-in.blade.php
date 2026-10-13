{{--
    "Continue with Google / GitHub", with an "or" under it, before the
    email fields of the sign-in and sign-up pages. Only providers whose
    credentials are set appear (SignInProvider::configured()); with none,
    nothing renders at all. "Continue with" is the wording Google accepts
    for signing in and signing up alike, and every provider gets the same
    button, as Google's guidelines ask of the ones shown beside it.

    On the sign-up page the buttons sit inside its form, under "I want
    to", and submit only to set off (formaction, formnovalidate): the side
    chosen there travels with them, so it is asked first for both ways of
    signing up. Elsewhere they are plain links.
--}}
@props(['inForm' => false])

@php
    $providers = \App\Enums\SignInProvider::configured();
@endphp

@if ($providers !== [])
    <div class="flex flex-col gap-3">
        @foreach ($providers as $provider)
            @if ($inForm)
                <x-social-button
                    :provider="$provider"
                    formaction="{{ route('social.start', $provider) }}"
                    formnovalidate
                    data-test="social-{{ $provider->value }}"
                >
                    {{ __('Continue with :provider', ['provider' => $provider->label()]) }}
                </x-social-button>
            @else
                <x-social-button
                    :provider="$provider"
                    :href="route('social.redirect', $provider)"
                    data-test="social-{{ $provider->value }}"
                >
                    {{ __('Continue with :provider', ['provider' => $provider->label()]) }}
                </x-social-button>
            @endif
        @endforeach
    </div>

    <div class="flex items-center gap-3 text-meta text-ink-muted">
        <span aria-hidden="true" class="h-px flex-1 bg-line"></span>
        {{ __('or') }}
        <span aria-hidden="true" class="h-px flex-1 bg-line"></span>
    </div>
@endif
