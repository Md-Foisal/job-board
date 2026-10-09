<x-layouts::app :title="__('Applications')">
    <x-page>
        <x-page-header :title="__('Applications')" />

        <x-tab-nav :label="__('Applications')">
            <x-tab-nav.item :href="route('candidate.applications.index')" :current="! $closed">{{ __('Active') }}</x-tab-nav.item>
            <x-tab-nav.item :href="route('candidate.applications.index', ['status' => \App\Enums\CandidateApplicationStatus::Closed->value])" :current="$closed">{{ __('Closed') }}</x-tab-nav.item>
        </x-tab-nav>

        {{-- One step of the Active tab, opened from a dashboard count. --}}
        @if ($status && ! $closed)
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="text-ink-muted">{{ __('Showing') }}</span>
                <x-chip>{{ __($status->label()) }}</x-chip>
                <flux:link :href="route('candidate.applications.index')" wire:navigate>{{ __('Show all active') }}</flux:link>
            </div>
        @endif

        @if ($applications->isEmpty() && ! $hasAny)
            <x-empty-state icon="paper-airplane" :heading="__('You haven\'t applied to any jobs yet.')" :action-href="route('jobs.index')" :action-label="__('Find jobs')">
                {{ __('Every job you apply to shows up here, with where your application stands.') }}
            </x-empty-state>
        @elseif ($applications->isEmpty() && $closed)
            <x-empty-state icon="archive-box" :heading="__('No closed applications.')" :action-href="route('candidate.applications.index')" :action-label="__('Show active applications')">
                {{ __('An application moves here once it ends: you were hired or not selected, or you withdrew.') }}
            </x-empty-state>
        @elseif ($applications->isEmpty() && $status)
            <x-empty-state icon="paper-airplane" :heading="__('No applications here right now.')" :action-href="route('candidate.applications.index')" :action-label="__('Show all active')" />
        @elseif ($applications->isEmpty())
            <x-empty-state icon="paper-airplane" :heading="__('Nothing in progress right now.')" :action-href="route('jobs.index')" :action-label="__('Find jobs')">
                {{ __('Your earlier applications are under Closed.') }}
            </x-empty-state>
        @else
            <x-card padding="none" class="divide-y divide-line overflow-hidden">
                @foreach ($applications as $application)
                    @php
                        $jobPosting = $application->jobPosting;
                        $appliedOn = \App\Support\LocalTime::of($application->created_at)->format(\App\Support\DateFormat::DAY);
                    @endphp

                    <a
                        href="{{ route('candidate.applications.show', $application) }}"
                        wire:navigate
                        class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6"
                    >
                        <x-company-logo :company="$jobPosting->company" size="sm" />

                        <div class="min-w-0 flex-1">
                            {{-- On a phone the lines wrap rather than cut off the
                                 date: the pill leaves the text little room. --}}
                            <p class="line-clamp-2 font-medium text-ink group-hover:text-sunset-small sm:truncate">{{ $jobPosting->title }}</p>
                            <p class="text-sm text-ink-muted sm:truncate">
                                {{ $jobPosting->company->name }}
                                &middot;
                                <time datetime="{{ $application->created_at->toAtomString() }}" title="{{ $appliedOn }}">{{ __('Applied :when', ['when' => $application->created_at->diffForHumans()]) }}</time>
                                {{-- Still waiting on a job that stopped taking
                                     applications: worth knowing, as Indeed's
                                     "Job closed" says. --}}
                                @if ($application->outcomeForCandidate() === \App\Enums\ApplicationOutcomeStatus::Active && ! $jobPosting->isOpen())
                                    &middot; {{ __('Job closed') }}
                                @endif
                            </p>
                        </div>

                        <x-application-status :application="$application" for-candidate />
                    </a>
                @endforeach
            </x-card>

            {{ $applications->links() }}
        @endif
    </x-page>
</x-layouts::app>
