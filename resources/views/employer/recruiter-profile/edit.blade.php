<x-layouts::employer :company="$company" :title="__('Your recruiter profile')">
    <div class="mx-auto flex max-w-2xl flex-col gap-8">
        <div>
            <flux:heading size="xl" class="font-display">{{ __('Your recruiter profile') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Candidates see this next to the jobs you post. It stays the same whichever company you are posting for, and leaving it empty simply shows the company name instead.') }}
            </flux:text>
        </div>

        <form method="POST" action="{{ route('employer.recruiter-profile.update') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
            @csrf
            @method('PATCH')

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <flux:avatar
                            size="lg"
                            :src="$recruiterProfile?->avatar_path ? \Illuminate\Support\Facades\Storage::url($recruiterProfile->avatar_path) : null"
                            :name="$recruiterProfile?->display_name ?: auth()->user()->name"
                            :initials="auth()->user()->initials()"
                        />
                        <flux:input type="file" name="avatar" :label="__('Photo')" :accept="\App\Support\ImageUploads::ACCEPT" class="flex-1" />
                    </div>

                    <flux:input
                        name="display_name"
                        :label="__('Display name')"
                        :value="old('display_name', $recruiterProfile?->display_name)"
                        :placeholder="auth()->user()->name"
                        :description="__('Leave empty to use your account name.')"
                    />

                    <flux:textarea
                        name="bio"
                        :label="__('Short introduction')"
                        rows="4"
                        :value="old('bio', $recruiterProfile?->bio)"
                        :placeholder="__('What you hire for, and how you like to work with candidates.')"
                    />
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::employer>
