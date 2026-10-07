<x-error-page
    code="429"
    icon="hand-raised"
    :heading="__('Too many tries')"
    :message="__('That was done too many times in a short while. Wait a minute, then try again.')"
>
    <flux:button :href="route('home')" variant="primary" class="btn-sunset">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
