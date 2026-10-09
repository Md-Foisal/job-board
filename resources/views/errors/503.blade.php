<x-error-page
    code="503"
    icon="wrench"
    shell="bare"
    :contact="false"
    :heading="__('We’ll be back shortly')"
    :message="__(':app is down for maintenance. Try again in a few minutes.', ['app' => config('app.name')])"
>
    <flux:button :href="url()->current()" variant="primary" class="btn-sunset" icon="arrow-path">
        {{ __('Try again') }}
    </flux:button>
</x-error-page>
