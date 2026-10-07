<x-layouts::auth :title="__('Check your email')">
    <x-auth-header
        :title="__('Check your email')"
        :description="__('We sent a link to :email. Open it to confirm the address is yours.', ['email' => auth()->user()->email])"
    />

    @if (session('status') == 'verification-link-sent')
        <flux:callout variant="success" icon="check-circle" :heading="__('We sent a new link. It can take a minute to arrive.')" />
    @endif

    <flux:text class="text-center">
        {{ __('Nothing there? Look in your spam folder, or send the link again.') }}
    </flux:text>

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary" class="btn-sunset w-full">
                {{ __('Send the link again') }}
            </flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button variant="ghost" type="submit" class="w-full" data-test="logout-button">
                {{ __('Log out') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
