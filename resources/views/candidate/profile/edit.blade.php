<x-layouts::app :title="__('Your profile')">
    <div class="mx-auto max-w-3xl">
        <flux:heading size="xl" level="1">{{ __('Your profile') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Everything about you in one place. When you apply, the company sees your name, headline, bio and skills, with the CV you attach.') }}</flux:subheading>

        <div
            x-data="{
                {{-- A section the server sent back with an error opens
                     again, so the message and the typed value are seen. --}}
                editingHeadline: @js($errors->has('headline')),
                editingBio: @js($errors->has('bio')),
                editingLinks: @js($errors->hasAny(['portfolio_url', 'github_url', 'linkedin_url'])),
                editingContact: @js($errors->hasAny(['phone', 'location'])),
                avatarPreview: null,
                coverPreview: null,
                pickAvatar(event) {
                    const file = event.target.files[0];
                    this.avatarPreview = file ? URL.createObjectURL(file) : null;
                },
                pickCover(event) {
                    const file = event.target.files[0];
                    this.coverPreview = file ? URL.createObjectURL(file) : null;
                },
                get hasPendingEdits() {
                    return this.editingHeadline || this.editingBio || this.editingLinks
                        || this.editingContact || this.avatarPreview || this.coverPreview;
                },
            }"
        >
            <x-card padding="none" class="overflow-hidden">
            <form
                method="POST"
                action="{{ route('candidate.profile.update') }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PATCH')

                {{-- Cover + avatar: same banner-with-overlap pattern as the
                     Company profile page, so the two "identity card" styles
                     match across the platform. --}}
                <div class="relative">
                    <div class="h-32 w-full overflow-hidden bg-surface sm:h-40">
                        @if ($candidateProfile->cover_photo_path)
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::url($candidateProfile->cover_photo_path) }}"
                                alt=""
                                class="size-full object-cover"
                                x-show="!coverPreview"
                            >
                        @else
                            <div class="bg-sunset absolute inset-0" x-show="!coverPreview">
                                <div
                                    class="absolute inset-0 opacity-[0.15]"
                                    style="background-image: radial-gradient(circle, white 1.5px, transparent 1.5px); background-size: 22px 22px;"
                                ></div>
                            </div>
                        @endif

                        <img
                            x-show="coverPreview"
                            :src="coverPreview"
                            alt=""
                            class="absolute inset-0 size-full object-cover"
                        >
                    </div>

                    <label
                        for="cover-upload"
                        class="absolute right-3 top-3 flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-xs font-medium text-white shadow-sm backdrop-blur hover:bg-black/55 cursor-pointer"
                        title="{{ __('Change cover photo') }}"
                    >
                        <flux:icon.camera variant="mini" class="size-3.5" />
                        {{ __('Cover') }}
                    </label>
                    <input
                        id="cover-upload"
                        type="file"
                        name="cover_photo"
                        accept="{{ \App\Support\ImageUploads::ACCEPT }}"
                        class="sr-only"
                        @change="pickCover($event)"
                    >

                    <div class="absolute -bottom-10 left-6 sm:-bottom-12 sm:left-8">
                        <div class="relative">
                            <div class="flex size-20 items-center justify-center overflow-hidden rounded-full border-4 border-canvas bg-brand-50 text-xl font-semibold shadow-md dark:bg-brand-950 sm:size-24 sm:text-2xl">
                                @if ($user->avatar)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::url($user->avatar) }}"
                                        alt="{{ $user->name }}"
                                        class="size-full object-cover"
                                        x-show="!avatarPreview"
                                    >
                                @else
                                    <span x-show="!avatarPreview" class="text-sunset-small">{{ $user->initials() }}</span>
                                @endif

                                <img
                                    x-show="avatarPreview"
                                    :src="avatarPreview"
                                    alt="{{ $user->name }}"
                                    class="absolute inset-0 size-full object-cover"
                                >
                            </div>

                            <label
                                for="avatar-upload"
                                class="absolute -bottom-1 -right-1 flex size-7 cursor-pointer items-center justify-center rounded-full border-2 border-canvas bg-ink text-canvas shadow-sm hover:bg-ink-soft"
                                title="{{ __('Change photo') }}"
                            >
                                <flux:icon.camera variant="mini" class="size-3.5" />
                            </label>
                            <input
                                id="avatar-upload"
                                type="file"
                                name="avatar"
                                accept="{{ \App\Support\ImageUploads::ACCEPT }}"
                                class="sr-only"
                                @change="pickAvatar($event)"
                            >
                        </div>
                    </div>
                </div>

                {{-- Name + headline --}}
                <div class="px-6 pb-2 pt-14 sm:px-8 sm:pt-16">
                    <div class="flex items-center gap-1.5">
                        <flux:heading size="xl" level="2">{{ $user->name }}</flux:heading>
                        <a
                            href="{{ route('profile.edit') }}"
                            wire:navigate
                            class="text-ink-muted hover:text-sunset-small"
                            title="{{ __('Edit your name (Account settings)') }}"
                        >
                            <flux:icon.pencil-square variant="mini" class="size-4" />
                        </a>
                </div>

                    {{-- Headline: read row (default) --}}
                    <div class="mt-1 flex items-center gap-1.5" x-show="!editingHeadline">
                        <p class="text-ink-muted">
                            {{ $candidateProfile->headline ?: __('Add a headline') }}
                        </p>
                        <button
                            type="button"
                            @click="editingHeadline = true; $nextTick(() => $refs.headlineInput.focus())"
                            class="text-ink-muted hover:text-sunset-small"
                            title="{{ __('Edit headline') }}"
                    >
                        <flux:icon.pencil-square variant="mini" class="size-4" />
                    </button>
                </div>

                    {{-- Headline: edit row --}}
                    <div class="mt-2 max-w-sm" x-show="editingHeadline" x-cloak>
                        <flux:input
                            x-ref="headlineInput"
                            @blur="editingHeadline = false"
                            name="headline"
                            :description="__('e.g. Senior Laravel Developer — shown right under your name')"
                            :value="old('headline', $candidateProfile->headline)"
                            maxlength="255"
                    />
                </div>

                {{-- The camera buttons above carry no room for a caption, so
                     the rules and any refusal are spelled out here. --}}
                <div class="mt-4 space-y-1 text-xs text-ink-muted">
                    <p>{{ __('Photo:') }} {{ \App\Support\ImageUploads::hint(\App\Support\ImageUploads::PHOTO) }}</p>
                    <p>{{ __('Cover:') }} {{ \App\Support\ImageUploads::hint(\App\Support\ImageUploads::COVER) }}</p>
                </div>
                @error('avatar')
                    <flux:text size="sm" class="mt-2 text-danger-700 dark:text-danger-300" role="alert">{{ $message }}</flux:text>
                @enderror
                @error('cover_photo')
                    <flux:text size="sm" class="mt-2 text-danger-700 dark:text-danger-300" role="alert">{{ $message }}</flux:text>
                @enderror
            </div>

            {{-- Bio --}}
            <div class="border-b border-t border-line px-6 py-6 sm:px-8">
                <div class="flex items-center gap-1.5" x-show="!editingBio">
                    <flux:subheading>{{ __('Bio') }}</flux:subheading>
                    <button
                        type="button"
                        @click="editingBio = true; $nextTick(() => $refs.bioInput.focus())"
                        class="text-ink-muted hover:text-sunset-small"
                        title="{{ __('Edit bio') }}"
                    >
                        <flux:icon.pencil-square variant="mini" class="size-4" />
                    </button>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-ink-muted" x-show="!editingBio">
                    {{ $candidateProfile->bio ?: __('Add a short bio so companies get a feel for who you are.') }}
                </p>

                <div x-show="editingBio" x-cloak>
                    <flux:textarea
                        x-ref="bioInput"
                        @blur="editingBio = false"
                        name="bio"
                        :label="__('Bio')"
                        rows="6"
                    >{{ old('bio', $candidateProfile->bio) }}</flux:textarea>
                </div>
            </div>

            {{-- Links --}}
            <div class="px-6 py-6 sm:px-8">
                <div class="flex items-center gap-1.5" x-show="!editingLinks">
                    <flux:subheading>{{ __('Links') }}</flux:subheading>
                    <button
                        type="button"
                        @click="editingLinks = true"
                        class="text-ink-muted hover:text-sunset-small"
                        title="{{ __('Edit links') }}"
                    >
                        <flux:icon.pencil-square variant="mini" class="size-4" />
                    </button>
                </div>

                <ul class="mt-2 space-y-2" x-show="!editingLinks">
                    <li class="flex items-center gap-2 text-sm">
                        <flux:icon name="globe-alt" variant="mini" class="size-4 shrink-0 text-ink-muted" />
                        @if ($candidateProfile->portfolio_url)
                            <a href="{{ $candidateProfile->portfolio_url }}" target="_blank" rel="noopener noreferrer" class="truncate text-sunset-small hover:underline">
                                {{ $candidateProfile->portfolio_url }}
                            </a>
                        @else
                            <span class="text-ink-muted">{{ __('Portfolio') }} — {{ __('not added yet') }}</span>
                    @endif
                    </li>
                    <li class="flex items-center gap-2 text-sm">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="size-4 shrink-0 text-ink-muted"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                        @if ($candidateProfile->github_url)
                            <a href="{{ $candidateProfile->github_url }}" target="_blank" rel="noopener noreferrer" class="truncate text-sunset-small hover:underline">
                                {{ $candidateProfile->github_url }}
                            </a>
                    @else
                        <span class="text-ink-muted">{{ __('GitHub') }} — {{ __('not added yet') }}</span>
                    @endif
                    </li>
                    <li class="flex items-center gap-2 text-sm">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="size-4 shrink-0 text-ink-muted"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        @if ($candidateProfile->linkedin_url)
                            <a href="{{ $candidateProfile->linkedin_url }}" target="_blank" rel="noopener noreferrer" class="truncate text-sunset-small hover:underline">
                                {{ $candidateProfile->linkedin_url }}
                            </a>
                    @else
                        <span class="text-ink-muted">{{ __('LinkedIn') }} — {{ __('not added yet') }}</span>
                    @endif
                    </li>
                </ul>

                <div class="space-y-6" x-show="editingLinks" x-cloak>
                    <flux:input
                        name="portfolio_url"
                        type="url"
                        :label="__('Portfolio URL')"
                        placeholder="https://"
                        :value="old('portfolio_url', $candidateProfile->portfolio_url)"
                    />
                    <flux:input
                        name="github_url"
                        type="url"
                        :label="__('GitHub URL')"
                        placeholder="https://github.com/username"
                        :value="old('github_url', $candidateProfile->github_url)"
                    />
                    <flux:input
                        name="linkedin_url"
                        type="url"
                        :label="__('LinkedIn URL')"
                        placeholder="https://linkedin.com/in/username"
                        :value="old('linkedin_url', $candidateProfile->linkedin_url)"
                    />
                </div>
            </div>

            {{-- Contact: kept for the CVs the candidate builds here, never
                 shown to companies on the profile itself. --}}
            <div class="border-t border-line px-6 py-6 sm:px-8">
                <div class="flex items-center gap-1.5" x-show="!editingContact">
                    <flux:subheading>{{ __('Contact for your CVs') }}</flux:subheading>
                    <button
                        type="button"
                        @click="editingContact = true; $nextTick(() => $refs.phoneInput.focus())"
                        class="text-ink-muted hover:text-sunset-small"
                        title="{{ __('Edit contact details') }}"
                    >
                        <flux:icon.pencil-square variant="mini" class="size-4" />
                    </button>
                </div>
                <flux:text size="sm" class="mt-1">{{ __("Shown on CVs you build here. Companies don't see these on your profile.") }}</flux:text>

                <ul class="mt-2 space-y-2" x-show="!editingContact">
                    <li class="flex items-center gap-2 text-sm">
                        <flux:icon name="phone" variant="mini" class="size-4 shrink-0 text-ink-muted" />
                        @if ($candidateProfile->phone)
                            <span class="text-ink-soft">{{ $candidateProfile->phone }}</span>
                        @else
                            <span class="text-ink-muted">{{ __('Phone') }} — {{ __('not added yet') }}</span>
                        @endif
                    </li>
                    <li class="flex items-center gap-2 text-sm">
                        <flux:icon name="map-pin" variant="mini" class="size-4 shrink-0 text-ink-muted" />
                        @if ($candidateProfile->location)
                            <span class="text-ink-soft">{{ $candidateProfile->location }}</span>
                        @else
                            <span class="text-ink-muted">{{ __('Location') }} — {{ __('not added yet') }}</span>
                        @endif
                    </li>
                </ul>

                <div class="mt-4 space-y-6" x-show="editingContact" x-cloak>
                    <flux:input
                        x-ref="phoneInput"
                        name="phone"
                        type="tel"
                        autocomplete="tel"
                        :label="__('Phone')"
                        :description="__('Include the country code, e.g. +44 for the UK')"
                        :value="old('phone', $candidateProfile->phone)"
                        maxlength="{{ \App\Support\ContactDetails::PHONE_MAX }}"
                    />
                    <flux:input
                        name="location"
                        :label="__('Location')"
                        :description="__('City and country is enough, e.g. Dhaka, Bangladesh')"
                        :value="old('location', $candidateProfile->location)"
                        maxlength="{{ \App\Support\ContactDetails::LOCATION_MAX }}"
                    />
                </div>
            </div>

                {{-- Save bar: only appears while something is actually being
                     edited (a section is open, or a new photo was picked) --
                     a full-time Save invited re-clicks with nothing new to
                     save, and every save reloads the page, which naturally
                     closes every section again and hides this bar. --}}
                <div
                    x-show="hasPendingEdits"
                    x-cloak
                    class="flex items-center gap-4 border-t border-line bg-surface px-6 py-4 sm:px-8"
                >
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <span class="text-xs text-ink-muted">{{ __('Saves everything above at once.') }}</span>
                </div>
            </form>
            </x-card>
        </div>

        {{-- Everything below is read-only here on purpose: Education,
             Experience, Skills and Documents each already have their own
             dedicated CRUD page (modal-based add/edit/delete).
             Duplicating that editing UI here would just be a
             second, out-of-sync place to do the same thing. This page's
             job is to show the candidate their whole profile at a glance,
             with a "Manage" link into the real editor for each section. --}}

        {{-- Education --}}
        <x-card padding="none" class="mt-6 overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-5 sm:px-8">
                <flux:subheading>{{ __('Education') }}</flux:subheading>
                <a href="{{ route('candidate.education.index') }}" wire:navigate class="text-sm font-medium text-sunset-small hover:underline">
                    {{ __('Manage') }}
                </a>
            </div>

            @if ($educationRecords->isEmpty())
                <div class="border-t border-line px-6 py-6 text-center sm:px-8">
                    <p class="text-sm text-ink-muted">{{ __("You haven't added any education yet.") }}</p>
                    <a href="{{ route('candidate.education.index') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-sunset-small hover:underline">
                        {{ __('+ Add education') }}
                    </a>
                </div>
            @else
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($educationRecords as $record)
                        <li class="flex gap-4 px-6 py-4 sm:px-8">
                            <x-icon-tile icon="academic-cap" />
                            <div>
                                <p class="font-medium text-ink">{{ $record->institution_name }}</p>
                                @if ($record->degree || $record->field_of_study)
                                    <p class="text-sm text-ink-muted">
                                        {{ collect([$record->degree, $record->field_of_study])->filter()->join(', ') }}
                                    </p>
                                @endif
                                <p class="mt-1 text-sm text-ink-muted">
                                    {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Experience --}}
        <x-card padding="none" class="mt-6 overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-5 sm:px-8">
                <flux:subheading>{{ __('Experience') }}</flux:subheading>
                <a href="{{ route('candidate.experience.index') }}" wire:navigate class="text-sm font-medium text-sunset-small hover:underline">
                    {{ __('Manage') }}
                </a>
            </div>

            @if ($experienceRecords->isEmpty())
                <div class="border-t border-line px-6 py-6 text-center sm:px-8">
                    <p class="text-sm text-ink-muted">{{ __("You haven't added any experience yet.") }}</p>
                    <a href="{{ route('candidate.experience.index') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-sunset-small hover:underline">
                        {{ __('+ Add experience') }}
                    </a>
                </div>
            @else
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($experienceRecords as $record)
                        <li class="flex gap-4 px-6 py-4 sm:px-8">
                            <x-icon-tile icon="briefcase" />
                            <div>
                                <p class="font-medium text-ink">{{ $record->job_title }}</p>
                                <p class="text-sm text-ink-muted">{{ $record->company_name }}</p>
                                <p class="mt-1 text-sm text-ink-muted">
                                    {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                                </p>
                                @if ($record->description)
                                    <div class="prose-content mt-2">{!! $record->description !!}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Skills --}}
        <x-card padding="none" class="mt-6 overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-5 sm:px-8">
                <flux:subheading>{{ __('Skills') }}</flux:subheading>
                <a href="{{ route('candidate.skills.edit') }}" wire:navigate class="text-sm font-medium text-sunset-small hover:underline">
                    {{ __('Manage') }}
                </a>
            </div>

            <div class="border-t border-line px-6 py-5 sm:px-8">
                @if ($skills->isEmpty())
                    <p class="text-sm text-ink-muted">{{ __("You haven't added any skills yet.") }}</p>
                    <a href="{{ route('candidate.skills.edit') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-sunset-small hover:underline">
                        {{ __('+ Add skills') }}
                    </a>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($skills as $skill)
                            <x-chip variant="skill">{{ $skill->name }}</x-chip>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Documents --}}
        <x-card padding="none" class="mt-6 overflow-hidden">
            <div class="flex items-center justify-between gap-4 px-6 py-5 sm:px-8">
                <flux:subheading>{{ __('Documents') }}</flux:subheading>
                <div class="flex items-center gap-4">
                    <a href="{{ route('candidate.cv-builder') }}" wire:navigate class="text-sm font-medium text-sunset-small hover:underline">
                        {{ __('Build a CV from your profile') }}
                    </a>
                    <a href="{{ route('candidate.documents.index') }}" wire:navigate class="text-sm font-medium text-sunset-small hover:underline">
                        {{ __('Manage') }}
                    </a>
                </div>
            </div>

            @if ($documents->isEmpty())
                <div class="border-t border-line px-6 py-6 text-center sm:px-8">
                    <p class="text-sm text-ink-muted">{{ __("You haven't added a CV or any documents yet.") }}</p>
                    <a href="{{ route('candidate.documents.index') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-sunset-small hover:underline">
                        {{ __('+ Add a document') }}
                    </a>
                </div>
            @else
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($documents as $document)
                        <li class="flex items-center justify-between gap-4 px-6 py-4 sm:px-8">
                            <div class="flex items-center gap-4">
                                <x-icon-tile :icon="$document->document_type->icon()" />
                                <div>
                                    <p class="font-medium text-ink">{{ $document->original_filename }}</p>
                                    <p class="text-sm text-ink-muted">{{ $document->document_type->label() }}</p>
                                </div>
                            </div>

                            <flux:button href="{{ route('candidate.documents.download', $document) }}" variant="ghost" size="sm" icon="arrow-down-tray" :aria-label="__('Download')" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts::app>
