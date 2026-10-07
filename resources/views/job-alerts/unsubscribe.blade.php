<x-layouts::auth :title="__('Unsubscribe')">
    <div class="flex flex-col gap-6 text-center">
        @if ($done || ! $jobAlert || ! $jobAlert->is_active)
            <x-auth-header
                :title="__('You\'re unsubscribed')"
                :description="$jobAlert
                    ? __('We won\'t email you about “:name” any more.', ['name' => $jobAlert->name])
                    : __('This job alert no longer exists, so it will not email you again.')"
            />

            @if ($jobAlert)
                <flux:text size="sm">
                    {{ __('Changed your mind? Sign in and resume it from your job alerts.') }}
                    <flux:link :href="route('candidate.job-alerts.index')">{{ __('Job alerts') }}</flux:link>
                </flux:text>
            @endif
        @else
            <x-auth-header
                :title="__('Stop this job alert?')"
                :description="__('You will no longer get emails about “:name”.', ['name' => $jobAlert->name])"
            />

            <form method="POST" action="{{ $action }}">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('Unsubscribe') }}</flux:button>
            </form>
        @endif
    </div>
</x-layouts::auth>
