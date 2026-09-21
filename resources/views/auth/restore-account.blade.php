<x-layouts::auth :title="__('Restore your account')">
    <div class="flex flex-col gap-6 text-center">
        <div class="flex flex-col gap-2">
            <flux:heading size="lg" class="font-display">{{ __('This account was deleted') }}</flux:heading>
            <flux:text>
                {{ __(':email is switched off and will be erased for good on :date. Restore it to carry on where you left off.', [
                    'email' => $email,
                    'date' => $erasesAt->toFormattedDateString(),
                ]) }}
            </flux:text>
        </div>

        <form method="POST" action="{{ route('account.restore.store') }}">
            @csrf
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Restore my account') }}</flux:button>
        </form>

        <flux:link :href="route('home')" class="text-sm">{{ __('Leave it deleted') }}</flux:link>
    </div>
</x-layouts::auth>
