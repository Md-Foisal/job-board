<x-error-page
    :code="$exception->getStatusCode()"
    icon="wrench-screwdriver"
    shell="bare"
    :heading="__('Something went wrong on our side')"
    :message="__('It wasn’t anything you did. Try again in a few minutes.')"
>
    <flux:button :href="url()->current()" variant="primary" class="btn-sunset" icon="arrow-path">
        {{ __('Try again') }}
    </flux:button>
    <flux:button :href="route('home')" variant="ghost">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
