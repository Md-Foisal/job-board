<x-layouts::auth :title="__('Set up your company')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 text-center">
            <flux:heading size="lg" class="font-display">{{ __('Set up your company') }}</flux:heading>
            <flux:text>{{ __('This is the name candidates will see on your job postings.') }}</flux:text>
        </div>

        <form method="POST" action="{{ route('companies.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="name"
                :label="__('Company name')"
                :value="old('name')"
                required
                autofocus
                autocomplete="organization"
            />

            <flux:radio.group name="identity_type" :label="__('What best describes you?')" variant="cards" class="flex-col" :value="old('identity_type', 'company')">
                <flux:radio value="company" :label="__('A company')" :description="__('Hiring for your own team.')" />
                <flux:radio value="individual" :label="__('An individual')" :description="__('Hiring on your own, without a registered business.')" />
                <flux:radio value="agency" :label="__('An agency')" :description="__('Hiring on behalf of other companies.')" />
            </flux:radio.group>

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Create company') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
