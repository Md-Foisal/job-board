{{--
    The dialogs behind the pencils on the candidate's own profile. Each
    is its own small form that sends only its own fields, so saving the
    About text never touches the links, and a mistake in one is shown in
    that dialog alone.
--}}
@php($update = route('candidate.profile.update'))

{{-- Photo --}}
<flux:modal name="edit-photo" class="w-full max-w-md">
    <form method="POST" action="{{ $update }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{ preview: null }">
        @csrf
        @method('PATCH')

        <div>
            <flux:heading size="lg">{{ $user->avatar ? __('Change photo') : __('Add a photo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('A clear photo of your face helps a company put a person to the name.') }}</flux:text>
        </div>

        <div class="flex items-center gap-4">
            <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-xl font-semibold dark:bg-brand-950">
                <template x-if="preview"><img :src="preview" alt="" class="size-full object-cover"></template>
                <template x-if="! preview">
                    @if ($user->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($user->avatar) }}" alt="" class="size-full object-cover">
                    @else
                        <span class="text-sunset-small">{{ $user->initials() }}</span>
                    @endif
                </template>
            </div>

            <flux:input
                type="file"
                name="avatar"
                accept="{{ \App\Support\ImageUploads::ACCEPT }}"
                :label="__('Photo')"
                :description="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::PHOTO)"
                x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                required
            />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save photo') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Cover --}}
<flux:modal name="edit-cover" class="w-full max-w-lg">
    <form method="POST" action="{{ $update }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{ preview: null }">
        @csrf
        @method('PATCH')

        <div>
            <flux:heading size="lg">{{ __('Edit cover') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The wide picture behind your photo. Companies do not see it; it is for your own page.') }}</flux:text>
        </div>

        <div class="relative h-24 overflow-hidden rounded-control bg-surface">
            <template x-if="preview"><img :src="preview" alt="" class="size-full object-cover"></template>
            <template x-if="! preview">
                @if ($profile->cover_photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($profile->cover_photo_path) }}" alt="" class="size-full object-cover">
                @else
                    <div class="bg-sunset absolute inset-0"></div>
                @endif
            </template>
        </div>

        <flux:input
            type="file"
            name="cover_photo"
            accept="{{ \App\Support\ImageUploads::ACCEPT }}"
            :label="__('Cover photo')"
            :description="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::COVER)"
            x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
            required
        />

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save cover') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Headline and links --}}
<flux:modal name="edit-intro" class="w-full max-w-lg">
    <form method="POST" action="{{ $update }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <flux:heading size="lg">{{ __('Edit intro') }}</flux:heading>

        <div>
            <flux:input :label="__('Name')" :value="$user->name" disabled />
            <flux:text size="sm" class="mt-2">
                {{ __('Your name belongs to your account.') }}
                <flux:link :href="route('profile.edit')" wire:navigate>{{ __('Change it in Settings') }}</flux:link>
            </flux:text>
        </div>

        <flux:input
            name="headline"
            :label="__('Headline')"
            :description="__('Shown under your name, e.g. Senior Laravel Developer')"
            :value="old('headline', $profile->headline)"
            maxlength="255"
        />

        <flux:input name="portfolio_url" type="url" :label="__('Portfolio')" placeholder="https://" :value="old('portfolio_url', $profile->portfolio_url)" />
        <flux:input name="github_url" type="url" :label="__('GitHub')" placeholder="https://github.com/username" :value="old('github_url', $profile->github_url)" />
        <flux:input name="linkedin_url" type="url" :label="__('LinkedIn')" placeholder="https://linkedin.com/in/username" :value="old('linkedin_url', $profile->linkedin_url)" />

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- About --}}
<flux:modal name="edit-about" class="w-full max-w-xl">
    <form method="POST" action="{{ $update }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <flux:heading size="lg">{{ __('About') }}</flux:heading>

        <flux:textarea
            name="bio"
            :label="__('About you')"
            :description="__('What you do, what you are good at, and what you are looking for.')"
            rows="8"
            maxlength="5000"
        >{{ old('bio', $profile->bio) }}</flux:textarea>

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Contact for CVs --}}
<flux:modal name="edit-contact" class="w-full max-w-md">
    <form method="POST" action="{{ $update }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <flux:heading size="lg">{{ __('Contact for your CVs') }}</flux:heading>
            <flux:text class="mt-1">{{ __("Only on CVs you build here. Companies don't see these on your profile.") }}</flux:text>
        </div>

        <flux:input
            name="phone"
            type="tel"
            autocomplete="tel"
            :label="__('Phone')"
            :description="__('Include the country code, e.g. +44 for the UK')"
            :value="old('phone', $profile->phone)"
            maxlength="{{ \App\Support\ContactDetails::PHONE_MAX }}"
        />
        <flux:input
            name="location"
            :label="__('Location')"
            :description="__('City and country is enough, e.g. Dhaka, Bangladesh')"
            :value="old('location', $profile->location)"
            maxlength="{{ \App\Support\ContactDetails::LOCATION_MAX }}"
        />

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>
