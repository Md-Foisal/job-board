{{-- Company setup sits in the app frame, not on a sign-in style page: it
     is reached from inside the app -- straight after registering to hire,
     or from the workspace menu to add a second company -- and needs a way
     back other than the logo. --}}
<x-layouts::app :title="__('Set up your company')">
    <x-page width="narrow">
        <x-page-header :title="__('Set up your company')" :back="$back" :back-label="$backLabel" />

        <x-card>
            <form method="POST" action="{{ route('companies.store') }}" class="flex flex-col gap-6">
                @csrf

                <flux:input
                    name="name"
                    :label="__('Company name')"
                    description:trailing="{{ __('Candidates see this name on every job you post.') }}"
                    :value="old('name')"
                    required
                    autofocus
                    autocomplete="organization"
                />

                {{-- The same names candidates see on the company page. Each radio
                     says whether it is the chosen one: Flux's radio group does
                     not pick one from a value of its own. --}}
                @php
                    $chosenType = old('identity_type', \App\Enums\IdentityType::Company->value);
                    $typeDescriptions = [
                        \App\Enums\IdentityType::Company->value => __('A business hiring for its own team.'),
                        \App\Enums\IdentityType::Individual->value => __('A person hiring on their own, without a registered business.'),
                        \App\Enums\IdentityType::Agency->value => __('A firm hiring on behalf of other companies.'),
                    ];
                @endphp
                <flux:radio.group name="identity_type" :label="__('Who is hiring?')" variant="cards" class="flex-col">
                    @foreach ([\App\Enums\IdentityType::Company, \App\Enums\IdentityType::Individual, \App\Enums\IdentityType::Agency] as $type)
                        <flux:radio :value="$type->value" :label="$type->label()" :description="$typeDescriptions[$type->value]" :checked="$chosenType === $type->value" />
                    @endforeach
                </flux:radio.group>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit" class="btn-sunset">
                        {{ __('Create company') }}
                    </flux:button>
                </div>
            </form>
        </x-card>
    </x-page>
</x-layouts::app>
