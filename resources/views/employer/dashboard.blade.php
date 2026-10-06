<x-layouts::employer :company="$company" :title="$company->name">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-display">{{ $company->name }}</flux:heading>
                <flux:text class="mt-1">{{ __('Where your hiring stands today.') }}</flux:text>
            </div>

            <flux:button icon="chart-bar" :href="route('employer.analytics', $company)" wire:navigate>
                {{ __('Analytics') }}
            </flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-card>
                <div class="text-sm text-ink-muted">{{ __('Live jobs') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-ink">{{ $openCount }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-ink-muted">{{ __('Applications') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-ink">{{ $applicationCount }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-ink-muted">{{ __('Waiting on you') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-sunset">{{ $newApplicationCount }}</div>
            </x-card>
        </div>

        @if ($reviewsAwaitingResponse > 0)
            <flux:callout icon="chat-bubble-left-right">
                <flux:callout.text>
                    {{ trans_choice(':count review of your hiring process is waiting for an answer.|:count reviews of your hiring process are waiting for an answer.', $reviewsAwaitingResponse, ['count' => $reviewsAwaitingResponse]) }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" :href="route('employer.reviews', ['company' => $company, 'show' => 'waiting'])" wire:navigate>{{ __('Read and answer') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <div>
            <flux:heading size="lg">{{ __('Your job postings') }}</flux:heading>

            @if ($jobPostings->isEmpty())
                <x-empty-state icon="briefcase" class="mt-4" :heading="__('No job postings yet.')">
                    {{ __('Post a job and it shows up here, with how many people have applied.') }}
                    @can('create', [\App\Models\JobPosting::class, $company])
                        <x-slot:actions>
                            <flux:button :href="route('employer.jobs.create', $company)" variant="primary" size="sm" wire:navigate>{{ __('Post a job') }}</flux:button>
                        </x-slot:actions>
                    @endcan
                </x-empty-state>
            @else
                <x-card padding="none" class="mt-4 overflow-hidden">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Job postings and how many people have applied') }}</caption>
                        <thead class="border-b border-line bg-surface">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-start font-medium text-ink-muted">{{ __('Job') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium text-ink-muted">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium text-ink-muted">{{ __('Applications') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium text-ink-muted">{{ __('New') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($jobPostings as $jobPosting)
                                <tr>
                                    <td class="px-5 py-4">
                                        <a
                                            href="{{ route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $jobPosting]) }}"
                                            class="font-medium text-ink hover:text-sunset-small hover:underline"
                                            wire:navigate
                                        >{{ $jobPosting->title }}</a>
                                    </td>
                                    <td class="px-5 py-4">
                                        <x-posting-status :job-posting="$jobPosting" :detailed="false" />
                                    </td>
                                    <td class="px-5 py-4 text-end tabular-nums text-ink-soft">{{ $jobPosting->applications_count }}</td>
                                    <td class="px-5 py-4 text-end tabular-nums font-medium {{ $jobPosting->new_applications_count > 0 ? 'text-sunset-small' : 'text-ink-muted' }}">
                                        {{ $jobPosting->new_applications_count }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::employer>
