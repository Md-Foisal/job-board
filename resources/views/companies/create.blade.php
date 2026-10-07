<x-layouts::auth :title="__('Set up your company')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Set up your company')"
            :description="__('This is the name candidates will see on your job postings.')"
        />

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
                {{-- The same names candidates see on the company page. --}}
                <flux:radio :value="\App\Enums\IdentityType::Company->value" :label="\App\Enums\IdentityType::Company->label()" :description="__('Hiring for your own team.')" />
                <flux:radio :value="\App\Enums\IdentityType::Individual->value" :label="\App\Enums\IdentityType::Individual->label()" :description="__('Hiring on your own, without a registered business.')" />
                <flux:radio :value="\App\Enums\IdentityType::Agency->value" :label="\App\Enums\IdentityType::Agency->label()" :description="__('Hiring on behalf of other companies.')" />
            </flux:radio.group>

            <flux:button variant="primary" type="submit" class="btn-sunset w-full">
                {{ __('Create company') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
