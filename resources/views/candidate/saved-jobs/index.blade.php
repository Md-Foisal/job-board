<x-layouts::app :title="__('Saved Jobs')">
    <div class="mx-auto max-w-6xl">
        <flux:heading size="xl" level="1">{{ __('Saved Jobs') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Jobs you bookmarked to come back to later.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        @if ($jobPostings->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __("You haven't saved any jobs yet.") }}
                <a href="{{ route('jobs.index') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Browse open roles') }} &rarr;
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($jobPostings as $jobPosting)
                    <x-job-card :job-posting="$jobPosting" :show-save-button="true" />
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
