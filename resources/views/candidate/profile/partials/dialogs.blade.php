{{--
    The dialogs behind the pencils on the candidate's own profile. Each
    is its own small form that sends only its own fields, so saving the
    About text never touches the links, and a mistake in one is shown in
    that dialog alone. Used by the profile page and the CV builder, which
    edit the same profile; the form returns to whichever page sent it.
--}}
@php
    $update = route('candidate.profile.update');

    // A form sent back with an error opens its dialog again, so the
    // message is seen next to what was typed.
    $reopen = match (true) {
        $errors->has('avatar') => 'edit-photo',
        $errors->has('cover_photo') => 'edit-cover',
        $errors->hasAny(['headline', 'portfolio_url', 'github_url', 'linkedin_url']) => 'edit-intro',
        $errors->has('bio') => 'edit-about',
        $errors->hasAny(['phone', 'location']) => 'edit-contact',
        default => null,
    };
@endphp

@if ($reopen)
    <div x-data x-init="$nextTick(() => $flux.modal(@js($reopen)).show())" hidden></div>
@endif

{{-- Photo --}}
<flux:modal name="edit-photo" class="w-full max-w-md">
    {{-- Nothing to save until a picture is picked. --}}
    <form method="POST" action="{{ $update }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{ picked: false }" x-on:image-picked="picked = !! $event.detail.url">
        @csrf
        @method('PATCH')

        <div>
            <flux:heading size="lg">{{ $user->avatar ? __('Change photo') : __('Add a photo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('A clear photo of your face helps a company put a person to the name.') }}</flux:text>
        </div>

        <x-image-picker
            name="avatar"
            :label="__('Photo')"
            :current="$user->avatar ? \Illuminate\Support\Facades\Storage::url($user->avatar) : null"
            :hint="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::PHOTO)"
        >
            <span class="text-xl font-semibold text-sunset-small">{{ $user->initials() }}</span>
        </x-image-picker>

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary" x-bind:disabled="! picked">{{ __('Save photo') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Cover --}}
<flux:modal name="edit-cover" class="w-full max-w-lg">
    <form method="POST" action="{{ $update }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{ picked: false }" x-on:image-picked="picked = !! $event.detail.url">
        @csrf
        @method('PATCH')

        <div>
            <flux:heading size="lg">{{ __('Edit cover') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The wide picture behind your photo. Companies do not see it; it is for your own page.') }}</flux:text>
        </div>

        <x-image-picker
            name="cover_photo"
            shape="wide"
            :label="__('Cover photo')"
            :current="$profile->cover_photo_path ? \Illuminate\Support\Facades\Storage::url($profile->cover_photo_path) : null"
            :hint="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::COVER)"
        >
            <div class="bg-sunset size-full"></div>
        </x-image-picker>

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary" x-bind:disabled="! picked">{{ __('Save cover') }}</flux:button>
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
