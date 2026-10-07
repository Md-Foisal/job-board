<x-layouts::app :title="__('Applications')">
    <x-page>
        <x-page-header :title="__('Applications')" />

        @if ($status)
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="text-ink-muted">{{ __('Showing') }}</span>
                <x-chip>{{ __($status->label()) }}</x-chip>
                <flux:link :href="route('candidate.applications.index')" wire:navigate>{{ __('Show all') }}</flux:link>
            </div>
        @endif

        @if ($applications->isEmpty() && $status)
            <x-empty-state icon="paper-airplane" :heading="__('No applications here right now.')" :action-href="route('candidate.applications.index')" :action-label="__('Show all applications')" />
        @elseif ($applications->isEmpty())
            <x-empty-state icon="paper-airplane" :heading="__('You haven\'t applied to any jobs yet.')" :action-href="route('jobs.index')" :action-label="__('Browse open roles')">
                {{ __('Every job you apply to shows up here, with where your application stands.') }}
            </x-empty-state>
        @else
            <x-card padding="none" class="divide-y divide-line">
                @foreach ($applications as $application)
                    <a
                        href="{{ route('candidate.applications.show', $application) }}"
                        wire:navigate
                        class="flex items-center justify-between gap-4 p-5 transition hover:bg-surface"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-display font-semibold text-ink">
                                {{ $application->jobPosting->title }}
                            </p>
                            <p class="truncate text-sm text-ink-muted">
                                {{ $application->jobPosting->company->name }}
                                &middot;
                                {{ __('Applied') }} {{ $application->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <x-application-status :application="$application" for-candidate />
                        </div>
                    </a>
                @endforeach
            </x-card>

            <div class="mt-8">
                {{ $applications->links() }}
            </div>
        @endif
    </x-page>
</x-layouts::app>
