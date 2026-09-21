<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto max-w-6xl">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Where things stand, at a glance.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-500">{{ __('Profile completion') }}</p>
                <p class="mt-2 font-display text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $profileCompletionPercent }}%</p>

                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-brand-600 dark:bg-brand-500" style="width: {{ $profileCompletionPercent }}%"></div>
                </div>

                @if ($profileCompletionPercent < 100)
                    {{-- Naming what's missing (not just the %) gives an actual
                         next action -- a bare number tells you how far but not
                         what to do about it. --}}
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-500">
                        {{ __('Missing:') }}
                        {{ $missingProfileItems->take(3)->join(', ') }}
                        @if ($missingProfileItems->count() > 3)
                            {{ __('+:count more', ['count' => $missingProfileItems->count() - 3]) }}
                        @endif
                    </p>
                    <a href="{{ route('candidate.profile.edit') }}" wire:navigate class="mt-1 inline-block text-sm text-brand-700 hover:underline dark:text-brand-400">
                        {{ __('Complete your profile') }} &rarr;
                    </a>
                @else
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-500">{{ __('Your profile is fully filled out.') }}</p>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-500">{{ __('Active applications') }}</p>
                <p class="mt-2 font-display text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $activeApplicationCount }}</p>

                <a href="{{ route('candidate.applications.index') }}" wire:navigate class="mt-3 inline-block text-sm text-brand-700 hover:underline dark:text-brand-400">
                    {{ __('View all applications') }} &rarr;
                </a>
            </div>
        </div>

        <flux:heading size="lg" level="2" class="mb-1 mt-10">{{ __('Jobs that match your skills') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Open roles you have not applied to, best fit first.') }}</flux:subheading>

        @if (! $hasSkills)
            {{-- Nothing to match on yet, so nothing is guessed: the only
                 useful thing to show is how to get a real list. --}}
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __('Add your skills and we will show the open jobs that fit you, with how well each one matches.') }}
                <a href="{{ route('candidate.skills.edit') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Add skills') }} &rarr;
                </a>
            </div>
        @elseif ($matches->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __('No open job asks for your skills right now.') }}
                <a href="{{ route('jobs.index') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Browse all open roles') }} &rarr;
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($matches as $match)
                    <x-job-card :job-posting="$match['jobPosting']" :match-score="$match['score']" :show-save-button="true" />
                @endforeach
            </div>
        @endif

        <flux:heading size="lg" level="2" class="mb-1 mt-10">{{ __('Recently viewed') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Jobs you looked at, in case you want another look.') }}</flux:subheading>

        @if ($recentlyViewedJobs->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __("You haven't viewed any jobs yet.") }}
                <a href="{{ route('jobs.index') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Browse open roles') }} &rarr;
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recentlyViewedJobs as $jobPosting)
                    <x-job-card :job-posting="$jobPosting" :match-score="$recentlyViewedScores[$jobPosting->id] ?? null" :show-save-button="true" />
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
