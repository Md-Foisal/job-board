<x-layouts::auth :title="__('Restore your account')">
    <div class="flex flex-col gap-6 text-center">
        <x-auth-header
            :title="__('This account was deleted')"
            :description="__(':email is switched off and will be erased for good on :date. Restore it to carry on where you left off.', [
                'email' => $email,
                'date' => \App\Support\LocalTime::of($erasesAt)->format(\App\Support\DateFormat::DAY),
            ])"
        />

        <form method="POST" action="{{ route('account.restore.store') }}">
            @csrf
            <flux:button variant="primary" type="submit" class="btn-sunset w-full">{{ __('Restore my account') }}</flux:button>
        </form>

        <flux:link :href="route('home')" class="text-sm">{{ __('Leave it deleted') }}</flux:link>
    </div>
</x-layouts::auth>
