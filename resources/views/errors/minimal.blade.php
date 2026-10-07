{{-- Laravel's own error views (401, 402) extend errors::minimal; this
     takes its place, so they come out in the site's frame too. --}}
<x-error-page
    :code="trim($__env->yieldContent('code'))"
    shell="bare"
    :heading="trim($__env->yieldContent('message'))"
    :message="__('Go back and try again, or start again from the homepage.')"
>
    <flux:button :href="route('home')" variant="primary" class="btn-sunset">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
