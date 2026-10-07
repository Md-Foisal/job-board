<?php

use Livewire\Component;

new class extends Component {}; ?>

<section aria-labelledby="delete-account-heading" class="flex flex-col gap-4 rounded-card border border-danger-300 bg-canvas p-6 dark:border-danger-950">
    <div>
        <flux:heading size="lg" level="2" id="delete-account-heading">{{ __('Delete account') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Your account closes straight away. For :days days you can sign in to restore it; after that it is erased.', ['days' => \App\Models\User::DELETION_GRACE_DAYS]) }}</flux:text>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" class="self-start" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
