<x-layouts::auth :title="__('Two-factor authentication')">
    <div
        class="flex flex-col gap-6"
        x-cloak
        x-data="{
            showRecoveryInput: @js($errors->has('recovery_code')),
            code: '',
            recovery_code: '',
            toggleInput() {
                this.showRecoveryInput = !this.showRecoveryInput;

                this.code = '';
                this.recovery_code = '';

                $nextTick(() => {
                    this.showRecoveryInput
                        ? this.$refs.recovery_code?.focus()
                        : this.$refs.code?.querySelector('input')?.focus();
                });
            },
        }"
    >
        <div x-show="!showRecoveryInput">
            <x-auth-header
                :title="__('Enter your code')"
                :description="__('Open your authenticator app and enter the 6-digit code it shows for :app.', ['app' => config('app.name')])"
            />
        </div>

        <div x-show="showRecoveryInput">
            <x-auth-header
                :title="__('Use a recovery code')"
                :description="__('Enter one of the recovery codes you saved when you turned on two-factor authentication. Each code works once.')"
            />
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <div x-show="!showRecoveryInput" class="flex justify-center">
                <flux:otp
                    x-ref="code"
                    x-model="code"
                    length="6"
                    name="code"
                    :label="__('Authentication code')"
                    label:sr-only
                    class="mx-auto"
                />
            </div>

            {{-- The field shows its own error under it, as every Flux
                 input does. --}}
            <div x-show="showRecoveryInput">
                <flux:input
                    type="text"
                    name="recovery_code"
                    :label="__('Recovery code')"
                    x-ref="recovery_code"
                    x-bind:required="showRecoveryInput"
                    autocomplete="one-time-code"
                    x-model="recovery_code"
                />
            </div>

            <flux:button variant="primary" type="submit" class="btn-sunset w-full">
                {{ __('Continue') }}
            </flux:button>
        </form>

        {{-- Real buttons, so the switch can be reached from the keyboard
             and is announced as something to press. --}}
        <p class="text-center text-sm text-ink-muted">
            <button type="button" x-show="!showRecoveryInput" @click="toggleInput()" class="font-medium text-sunset-small hover:underline">
                {{ __('Use a recovery code instead') }}
            </button>
            <button type="button" x-show="showRecoveryInput" @click="toggleInput()" class="font-medium text-sunset-small hover:underline">
                {{ __('Use your authenticator app instead') }}
            </button>
        </p>
    </div>
</x-layouts::auth>
