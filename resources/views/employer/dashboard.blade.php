<x-layouts::employer :company="$company" :title="$company->name">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
        <div>
            <flux:heading size="xl" class="font-display">{{ $company->name }}</flux:heading>
            <flux:text class="mt-1">{{ __('Where your hiring stands today.') }}</flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open jobs') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $openCount }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Applications') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $applicationCount }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Waiting on you') }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums text-brand-700 dark:text-brand-400">{{ $newApplicationCount }}</div>
            </div>
        </div>

        <div>
            <flux:heading size="lg">{{ __('Your job postings') }}</flux:heading>

            @if ($jobPostings->isEmpty())
                <div class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __('No job postings yet.') }}</flux:text>
                </div>
            @else
                <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Job postings and how many people have applied') }}</caption>
                        <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Job') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Applications') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('New') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($jobPostings as $jobPosting)
                                <tr>
                                    <td class="px-5 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $jobPosting->title }}</td>
                                    <td class="px-5 py-4">
                                        <flux:badge :color="$jobPosting->availability_status === \App\Enums\AvailabilityStatus::Active ? 'green' : 'zinc'">
                                            {{ $jobPosting->availability_status->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ $jobPosting->applications_count }}</td>
                                    <td class="px-5 py-4 text-end tabular-nums font-medium {{ $jobPosting->new_applications_count > 0 ? 'text-brand-700 dark:text-brand-400' : 'text-zinc-400' }}">
                                        {{ $jobPosting->new_applications_count }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::employer>
