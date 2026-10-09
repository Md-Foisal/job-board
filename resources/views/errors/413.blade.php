{{-- The form is cut off before any session starts, so this page cannot
     send anyone back with a message, and it has no session to draw the
     navbar from; the browser's own referrer is the one thing that still
     knows where they were. --}}
<x-error-page
    code="413"
    icon="arrow-up-tray"
    shell="bare"
    :heading="__('That upload was too large')"
    :message="__('Nothing was saved. Go back and choose a smaller file — the size limit is shown next to each upload field.')"
>
    <flux:button :href="url()->previous()" variant="primary" class="btn-sunset" icon="arrow-left">
        {{ __('Back to the form') }}
    </flux:button>
</x-error-page>
