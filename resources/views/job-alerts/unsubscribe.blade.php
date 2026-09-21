<x-layouts::auth :title="__('Unsubscribe')">
    <div class="flex flex-col gap-6 text-center">
        @if ($done || ! $jobAlert || ! $jobAlert->is_active)
            <div class="flex flex-col gap-2">
                <flux:heading size="lg" class="font-display">{{ __("You're unsubscribed") }}</flux:heading>
                <flux:text>
                    @if ($jobAlert)
                        {{ __('We won\'t email you about ":name" any more.', ['name' => $jobAlert->name]) }}
                    @else
                        {{ __('This job alert no longer exists, so it will not email you again.') }}
                    @endif
                </flux:text>
            </div>

            @if ($jobAlert)
                <flux:text size="sm">
                    {{ __('Changed your mind? Sign in and resume it from your job alerts.') }}
                    <flux:link :href="route('candidate.job-alerts.index')">{{ __('Job alerts') }}</flux:link>
                </flux:text>
            @endif
        @else
            <div class="flex flex-col gap-2">
                <flux:heading size="lg" class="font-display">{{ __('Stop this job alert?') }}</flux:heading>
                <flux:text>{{ __('You will no longer get emails about ":name".', ['name' => $jobAlert->name]) }}</flux:text>
            </div>

            <form method="POST" action="{{ $action }}">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('Unsubscribe') }}</flux:button>
            </form>
        @endif
    </div>
</x-layouts::auth>
