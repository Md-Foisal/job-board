{{--
    The recruiter's own face: a photo, a name and a line or two, shown as
    "Posted by" on every job they post. The card candidates will see sits
    beside the form and changes as it is typed, so nobody has to save and
    open a job page to check how it reads. Without a profile the job page
    falls back to the account's own name and photo, and so does the
    preview.
--}}
@php
    $user = auth()->user();
    $photo = $recruiterProfile?->avatar_path ?: $user->avatar;
    $photoUrl = $photo ? \Illuminate\Support\Facades\Storage::url($photo) : null;
@endphp

<x-layouts::employer :company="$company" :title="__('Your recruiter profile')">
    <x-page>
        <x-page-header :title="__('Your recruiter profile')">
            <x-slot:status>
                <x-visibility-badge public :tip="__('Candidates see this next to the jobs you post. It stays the same whichever company you are posting for. Until you fill it in, they see your account name.')" />
            </x-slot:status>
        </x-page-header>

        <div
            class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5"
            x-data="{
                name: @js(old('display_name', $recruiterProfile?->display_name) ?? ''),
                bio: @js(old('bio', $recruiterProfile?->bio) ?? ''),
                photo: @js($photoUrl),
                fallbackName: @js($user->name),
            }"
            x-on:image-picked="if ($event.detail.name === 'avatar') photo = $event.detail.url ?? @js($photoUrl)"
        >
            <form method="POST" action="{{ route('employer.recruiter-profile.update') }}" enctype="multipart/form-data" class="flex flex-col gap-6 lg:col-span-3">
                @csrf
                @method('PATCH')

                <x-card>
                    <div class="flex flex-col gap-6">
                        <x-image-picker
                            name="avatar"
                            :label="__('Photo')"
                            :current="$recruiterProfile?->avatar_path ? \Illuminate\Support\Facades\Storage::url($recruiterProfile->avatar_path) : null"
                            :hint="\App\Support\ImageUploads::hint(\App\Support\ImageUploads::PHOTO)"
                        >
                            <span class="text-xl font-semibold text-sunset-small">{{ $user->initials() }}</span>
                        </x-image-picker>

                        <flux:input
                            name="display_name"
                            :label="__('Display name')"
                            :value="old('display_name', $recruiterProfile?->display_name)"
                            :placeholder="$user->name"
                            :description="__('Leave empty to use your account name.')"
                            x-on:input="name = $event.target.value"
                        />

                        <flux:textarea
                            name="bio"
                            :label="__('Short introduction')"
                            rows="4"
                            :placeholder="__('What you hire for, and how you like to work with candidates.')"
                            x-on:input="bio = $event.target.value"
                        >{{ old('bio', $recruiterProfile?->bio) }}</flux:textarea>
                    </div>
                </x-card>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                </div>
            </form>

            {{-- The same parts as the job page's "Posted by" line, under the
                 company it is posted for. --}}
            <aside class="lg:sticky lg:top-20 lg:col-span-2" aria-labelledby="posted-by-preview">
                <h2 id="posted-by-preview" class="mb-3 text-sm font-medium text-ink-muted">{{ __('What candidates see on your jobs') }}</h2>

                <x-card aria-live="polite">
                    @if ($company)
                        <div class="flex items-center gap-3">
                            <x-company-logo :company="$company" />
                            <p class="min-w-0 truncate text-subheading text-ink">{{ $company->name }}</p>
                        </div>
                    @endif

                    <div @class(['flex items-start gap-3', 'mt-5 border-t border-line pt-4' => $company])>
                        <span class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface text-xs font-medium text-ink-soft ring-1 ring-line">
                            <template x-if="photo"><img :src="photo" alt="" class="size-full object-cover"></template>
                            <span x-show="! photo">{{ $user->initials() }}</span>
                        </span>

                        <div class="min-w-0 text-sm">
                            <p class="text-meta text-ink-muted">{{ __('Posted by') }}</p>
                            <p class="font-medium text-ink" x-text="name.trim() || fallbackName">{{ old('display_name', $recruiterProfile?->display_name) ?: $user->name }}</p>
                            <p class="mt-1 whitespace-pre-line text-ink-muted" x-show="bio.trim()" x-text="bio">{{ old('bio', $recruiterProfile?->bio) }}</p>
                        </div>
                    </div>
                </x-card>
            </aside>
        </div>
    </x-page>
</x-layouts::employer>
