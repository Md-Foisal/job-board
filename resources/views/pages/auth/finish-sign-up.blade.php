{{-- The last step of signing up with Google or GitHub
     (SocialSignUpController): the side they are joining for and the name
     to show. The email address is the one the provider vouched for, so it
     is shown, not asked. Nothing exists until "Create account". --}}
<x-layouts::auth :title="__('Finish creating your account')">
    <x-auth-header
        :title="__('Finish creating your account')"
        :description="__('Signing up with :provider as :email.', ['provider' => $provider->label(), 'email' => $email])"
    />

    <form method="POST" action="{{ route('social.finish.store') }}" class="flex flex-col gap-6">
        @csrf

        <x-role-choice :selected="old('role', $role)" />

        <flux:input
            name="name"
            :label="__('Full name')"
            :value="old('name', $name)"
            type="text"
            required
            autofocus
            autocomplete="name"
        />

        <div class="flex flex-col gap-3">
            <flux:button type="submit" variant="primary" class="btn-sunset w-full" data-test="finish-sign-up-button">
                {{ __('Create account') }}
            </flux:button>

            <p class="text-center text-meta text-ink-muted">
                {{ __('By creating an account, you agree to our') }}
                <a href="{{ route('terms') }}" class="text-sunset-small hover:underline">{{ __('Terms') }}</a>
                {{ __('and') }}
                <a href="{{ route('privacy') }}" class="text-sunset-small hover:underline">{{ __('Privacy Policy') }}</a>.
            </p>
        </div>
    </form>

    <x-slot:footer>
        {{ __('Already have an account?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </x-slot:footer>
</x-layouts::auth>
