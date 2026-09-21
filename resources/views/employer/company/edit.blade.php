<x-layouts::employer :company="$company" :title="__('Company profile')">
    <div class="mx-auto flex max-w-3xl flex-col gap-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-display">{{ __('Company profile') }}</flux:heading>
                <flux:text class="mt-1">{{ __('This is what candidates see before they decide to apply.') }}</flux:text>
            </div>

            {{-- Verification is the platform's judgement, not the company's,
                 so it is shown here rather than edited: a company that has
                 been asked for documents otherwise has no way of knowing. --}}
            @if ($company->verified_at)
                <flux:badge color="green">{{ __('Verified') }}</flux:badge>
            @elseif ($company->outstandingDocumentsRequest())
                <flux:badge color="amber">{{ __('Documents requested') }}</flux:badge>
            @else
                <flux:badge color="zinc">{{ __('Pending verification') }}</flux:badge>
            @endif
        </div>

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

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
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
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
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
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Branding') }}</flux:heading>

                <div class="mt-6 flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-50 font-display text-lg font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                            @if ($company->logo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ __('Current logo of :company', ['company' => $company->name]) }}" class="size-full object-cover">
                            @else
                                {{ \Illuminate\Support\Str::of($company->name)->substr(0, 1) }}
                            @endif
                        </div>
                        <flux:input type="file" name="logo" :label="__('Logo')" :accept="\App\Support\ImageUploads::ACCEPT" class="flex-1" />
                    </div>

                    <flux:input type="file" name="cover_photo" :label="__('Cover photo')" :accept="\App\Support\ImageUploads::ACCEPT" />
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::employer>
