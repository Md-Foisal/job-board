@php
    // A policy or middleware that gives a reason ("Two-factor
    // authentication is required for staff accounts.") is shown it;
    // Laravel's own "This action is unauthorized." says nothing a person
    // can act on, so it gets the general sentence instead.
    $reason = $exception->getMessage();
    $message = filled($reason) && $reason !== 'This action is unauthorized.'
        ? $reason
        : __('This page is for a different kind of account, or for people with a different role in a company.');
@endphp

<x-error-page
    code="403"
    icon="lock-closed"
    :heading="__('You don’t have access to this page')"
    :message="$message"
>
    <flux:button :href="route('home')" variant="primary" class="btn-sunset">
        {{ __('Go to the homepage') }}
    </flux:button>
    @guest
        <flux:button :href="route('login')" variant="ghost">
            {{ __('Log in') }}
        </flux:button>
    @endguest
</x-error-page>
