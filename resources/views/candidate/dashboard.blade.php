<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto max-w-6xl">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Where things stand, at a glance.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-card>
                <p class="text-sm font-medium text-ink-muted">{{ __('Profile completion') }}</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ $profileCompletionPercent }}%</p>

                <x-meter :percent="$profileCompletionPercent" size="sm" class="mt-3" />

                @if ($profileCompletionPercent < 100)
                    {{-- Naming what's missing (not just the %) gives an actual
                         next action -- a bare number tells you how far but not
                         what to do about it. --}}
                    <p class="mt-3 text-sm text-ink-muted">
                        {{ __('Missing:') }}
                        {{ $missingProfileItems->take(3)->join(', ') }}
                        @if ($missingProfileItems->count() > 3)
                            {{ __('+:count more', ['count' => $missingProfileItems->count() - 3]) }}
                        @endif
                    </p>
                    <a href="{{ route('candidate.profile.edit') }}" wire:navigate class="mt-1 inline-block text-sm text-sunset-small hover:underline">
                        {{ __('Complete your profile') }} &rarr;
                    </a>
                @else
                    <p class="mt-3 text-sm text-ink-muted">{{ __('Your profile is fully filled out.') }}</p>
                @endif
            </x-card>

            <x-card>
                <p class="text-sm font-medium text-ink-muted">{{ __('Active applications') }}</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ $activeApplicationCount }}</p>

                <a href="{{ route('candidate.applications.index') }}" wire:navigate class="mt-3 inline-block text-sm text-sunset-small hover:underline">
                    {{ __('View all applications') }} &rarr;
                </a>
            </x-card>
        </div>

        <flux:heading size="lg" level="2" class="mb-1 mt-10">{{ __('Jobs that match your skills') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Open roles you have not applied to, best fit first.') }}</flux:subheading>

        @if (! $hasSkills)
            {{-- Nothing to match on yet, so nothing is guessed: the only
                 useful thing to show is how to get a real list. --}}
            <x-empty-state icon="sparkles" :level="3" :heading="__('See the jobs that fit you')" :action-href="route('candidate.skills.edit')" :action-label="__('Add skills')">
                {{ __('Add your skills and we will show the open jobs that fit you, with how well each one matches.') }}
            </x-empty-state>
        @elseif ($matches->isEmpty())
            <x-empty-state icon="magnifying-glass" :level="3" :heading="__('No open job asks for your skills right now.')" :action-href="route('jobs.index')" :action-label="__('Browse all open roles')" />
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
            <x-empty-state icon="eye" :level="3" :heading="__('You haven\'t viewed any jobs yet.')" :action-href="route('jobs.index')" :action-label="__('Browse open roles')" />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recentlyViewedJobs as $jobPosting)
                    <x-job-card :job-posting="$jobPosting" :match-score="$recentlyViewedScores[$jobPosting->id] ?? null" :show-save-button="true" />
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
