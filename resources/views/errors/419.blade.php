{{-- Laravel's CSRF check: a form left open past the session's lifetime,
     or submitted after signing out in another tab. --}}
<x-error-page
    code="419"
    icon="clock"
    :heading="__('This page timed out')"
    :message="__('It was open for a long time, so for your security the form expired. Go back, reload the page and try again.')"
>
    <flux:button :href="url()->previous()" variant="primary" class="btn-sunset" icon="arrow-left">
        {{ __('Go back') }}
    </flux:button>
    <flux:button :href="route('home')" variant="ghost">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
