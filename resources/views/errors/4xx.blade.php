{{-- Any other client error (405, 410…). Drawn bare: some of them are
     refused before a session exists. --}}
<x-error-page
    :code="$exception->getStatusCode()"
    shell="bare"
    :heading="__('That didn’t work')"
    :message="__('The request couldn’t be completed. Go back and try again, or start again from the homepage.')"
>
    <flux:button :href="route('home')" variant="primary" class="btn-sunset">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
