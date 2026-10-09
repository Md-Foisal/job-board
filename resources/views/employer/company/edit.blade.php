<x-layouts::employer :company="$company" :title="__('Company profile')">
    <x-page width="narrow">
        <x-page-header :title="__('Company profile')">
            {{-- Verification is the platform's judgement, not the company's,
                 so it is shown here rather than edited: a company that has
                 been asked for documents otherwise has no way of knowing. --}}
            <x-slot:status>
                <x-visibility-badge public :tip="__('This is what candidates see before they decide to apply.')" />
                <x-verification-status :company="$company" />
            </x-slot:status>

            <x-slot:actions>
                <flux:button size="sm" variant="ghost" icon="eye" :href="route('companies.show', $company)" wire:navigate>{{ __('View public page') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        @if ($documentsRequest = $company->outstandingDocumentsRequest())
            <flux:callout icon="document-text" color="amber">
                <flux:callout.heading>{{ __('Documents requested') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Our team needs more before verifying :company:', ['company' => $company->name]) }}
                    <span class="mt-2 block whitespace-pre-line">{{ $documentsRequest }}</span>
                    <span class="mt-2 block">{{ __('Reply to the email we sent with what was asked for.') }}</span>
                </flux:callout.text>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('employer.company.update', $company) }}" enctype="multipart/form-data" class="flex flex-col gap-8">
            @csrf
            @method('PATCH')

            <x-card>
                <flux:heading size="lg">{{ __('Identity') }}</flux:heading>

                <div class="mt-6 flex flex-col gap-6">
                    <flux:input name="name" :label="__('Company name')" :value="old('name', $company->name)" required />

                    @php($currentType = old('identity_type', $company->identity_type->value))
                    <flux:select name="identity_type" :label="__('Type')">
                        @foreach (\App\Enums\IdentityType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}" :selected="$currentType === $type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input type="url" name="website_url" :label="__('Website')" :value="old('website_url', $company->website_url)" placeholder="https://example.com" />
                </div>
            </x-card>

            <x-card>
                <flux:heading size="lg">{{ __('About') }}</flux:heading>

                <div class="mt-6 flex flex-col gap-6">
                    <x-rich-text-editor
                        name="description"
                        :value="old('description', $company->description)"
                        :label="__('Description')"
                        :description="__('What the company does, and what it is like to work there.')"
                    />

                    <div class="grid gap-6 sm:grid-cols-2">
                        @php($currentSize = old('size', $company->size))
                        <flux:select name="size" :label="__('Company size')">
                            <flux:select.option value="" :selected="blank($currentSize)">{{ __('Prefer not to say') }}</flux:select.option>
                            @foreach (['1-10', '11-50', '51-200', '200+'] as $bracket)
                                <flux:select.option value="{{ $bracket }}" :selected="$currentSize === $bracket">{{ $bracket }} {{ __('people') }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input name="industry" :label="__('Industry')" :value="old('industry', $company->industry)" placeholder="{{ __('Software, logistics, retail...') }}" />
                    </div>

                    @php($currentZone = old('timezone', $company->timezone))
                    <flux:select
                        name="timezone"
                        :label="__('Time zone')"
                        :description="__('Your closing dates run to the end of the day in this zone, and your analytics count days by it, so everyone on the team sees the same numbers. A change applies to the days counted from then on.')"
                    >
                        @foreach (\App\Support\LocalTime::choices() as $region => $zones)
                            <optgroup label="{{ $region }}">
                                @foreach ($zones as $zone => $label)
                                    <flux:select.option value="{{ $zone }}" :selected="$currentZone === $zone">{{ $label }}</flux:select.option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </flux:select>
                </div>
            </x-card>

            <x-card>
                <flux:heading size="lg">{{ __('Branding') }}</flux:heading>

                <div class="mt-6 flex flex-col gap-8">
                    <x-image-picker
                        name="logo"
                        shape="square"
                        :label="__('Logo')"
                        :current="$company->logo_path ? \Illuminate\Support\Facades\Storage::url($company->logo_path) : null"
                        :hint="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::LOGO)"
                    >
                        <x-company-logo :company="$company" size="lg" />
                    </x-image-picker>

                    <x-image-picker
                        name="cover_photo"
                        shape="wide"
                        :label="__('Cover photo')"
                        :current="$company->cover_photo_path ? \Illuminate\Support\Facades\Storage::url($company->cover_photo_path) : null"
                        :hint="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::COVER)"
                    >
                        <div class="bg-sunset size-full"></div>
                    </x-image-picker>
                </div>
            </x-card>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </x-page>
</x-layouts::employer>
