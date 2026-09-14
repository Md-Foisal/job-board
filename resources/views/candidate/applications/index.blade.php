<x-layouts::app :title="__('My Applications')">
    <div class="mx-auto max-w-4xl">
        <flux:heading size="xl" level="1">{{ __('My Applications') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Every job you have applied to, in one place.') }}</flux:subheading>
        <flux:separator variant="subtle" class="mb-6" />

        @if ($applications->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                {{ __("You haven't applied to any jobs yet.") }}
                <a href="{{ route('jobs.index') }}" class="text-brand-700 hover:underline dark:text-brand-400" wire:navigate>
                    {{ __('Browse open roles') }} &rarr;
                </a>
            </div>
        @else
            <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach ($applications as $application)
                    <a
                        href="{{ route('jobs.show', $application->jobPosting) }}"
                        wire:navigate
                        class="flex items-center justify-between gap-4 p-5 transition hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-display font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $application->jobPosting->title }}
                            </p>
                            <p class="truncate text-sm text-zinc-500 dark:text-zinc-500">
                                {{ $application->jobPosting->company->name }}
                                &middot;
                                {{ __('Applied') }} {{ $application->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <flux:badge :color="match ($application->outcome_status) {
                                \App\Enums\ApplicationOutcomeStatus::Active => 'blue',
                                \App\Enums\ApplicationOutcomeStatus::Hired => 'green',
                                \App\Enums\ApplicationOutcomeStatus::Rejected => 'red',
                                \App\Enums\ApplicationOutcomeStatus::Withdrawn => 'zinc',
                            }">
                                {{ $application->outcome_status->label() }}
                            </flux:badge>

                            @if ($application->outcome_status === \App\Enums\ApplicationOutcomeStatus::Active)
                                <flux:badge color="zinc" variant="pill">
                                    {{ $application->stage->label() }}
                                </flux:badge>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $applications->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
