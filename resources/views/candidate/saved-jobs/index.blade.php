<x-layouts::app :title="__('Saved Jobs')">
    <div class="mx-auto max-w-6xl">
        <flux:heading size="xl" level="1">{{ __('Saved Jobs') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Jobs you bookmarked to come back to later.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        @if ($unavailableCount > 0)
            <div class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <flux:text>
                    {{ trans_choice('{1} One job you saved is no longer available, so it is not shown.|[2,*] :count jobs you saved are no longer available, so they are not shown.', $unavailableCount) }}
                </flux:text>
                <form method="POST" action="{{ route('candidate.saved-jobs.prune') }}">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" size="sm" variant="ghost" icon="trash">
                        {{ trans_choice('{1} Remove it|[2,*] Remove them', $unavailableCount) }}
                    </flux:button>
                </form>
            </div>
        @endif

        @if ($jobPostings->isEmpty() && $unavailableCount > 0)
            {{-- Saying "you haven't saved any" here would contradict the
                 line just above it. --}}
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __('None of the jobs you saved is open right now.') }}
                <a href="{{ route('jobs.index') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Browse open roles') }} &rarr;
                </a>
            </div>
        @elseif ($jobPostings->isEmpty())
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
