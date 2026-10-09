{{-- The message never echoes the exception's: for a missing model it
     names the model class. --}}
<x-error-page
    code="404"
    icon="map"
    :heading="__('We can’t find that page')"
    :message="__('The link may be broken, or the job may have closed or been taken down. You can search the jobs that are open now.')"
>
    <flux:button :href="route('jobs.index')" variant="primary" class="btn-sunset" icon="magnifying-glass">
        {{ __('Search jobs') }}
    </flux:button>
    <flux:button :href="route('home')" variant="ghost">
        {{ __('Go to the homepage') }}
    </flux:button>
</x-error-page>
