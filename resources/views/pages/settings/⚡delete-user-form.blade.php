<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6 rounded-xl border border-red-200 bg-white p-6 dark:border-red-900/50 dark:bg-zinc-900">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
