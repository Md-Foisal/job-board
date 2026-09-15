<?php

use App\Actions\DuplicateJobPosting;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Models\Company;
use App\Models\JobPosting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Job postings')] class extends Component {
    public Company $company;

    public string $filter = 'all';

    public function mount(Company $company): void
    {
        $this->company = $company;
    }

    #[Computed]
    public function jobPostings()
    {
        return $this->company->jobPostings()
            ->when($this->filter !== 'all', fn ($query) => $query->where('availability_status', $this->filter))
            ->withCount([
                'applications',
                'applications as new_applications_count' => fn ($query) => $query->where('stage', ApplicationStage::New),
            ])
            ->latest()
            ->get();
    }

    public function close(int $jobPostingId): void
    {
        $jobPosting = $this->find($jobPostingId);
        $this->authorize('close', $jobPosting);

        $jobPosting->availability_status = AvailabilityStatus::Closed;
        $jobPosting->save();

        unset($this->jobPostings);
        Flux::toast(variant: 'success', text: __('Posting closed.'));
    }

    public function reopen(int $jobPostingId): void
    {
        $jobPosting = $this->find($jobPostingId);
        $this->authorize('reopen', $jobPosting);

        // Reopening something already past its date would put it straight
        // back into the expired pile, so the date moves with it.
        if ($jobPosting->expires_at->isPast()) {
            $jobPosting->expires_at = now()->addMonth();
        }

        $jobPosting->availability_status = AvailabilityStatus::Active;
        $jobPosting->save();

        unset($this->jobPostings);
        Flux::toast(variant: 'success', text: __('Posting reopened.'));
    }

    public function extend(int $jobPostingId): void
    {
        $jobPosting = $this->find($jobPostingId);
        $this->authorize('extend', $jobPosting);

        // Extend from today rather than from the old date: a posting that
        // lapsed last month should get a full month, not three days.
        $from = $jobPosting->expires_at->isPast() ? now() : $jobPosting->expires_at;

        $jobPosting->expires_at = $from->addMonth();

        if ($jobPosting->availability_status === AvailabilityStatus::Expired) {
            $jobPosting->availability_status = AvailabilityStatus::Active;
        }

        $jobPosting->save();

        unset($this->jobPostings);
        Flux::toast(variant: 'success', text: __('Closing date moved to :date.', [
            'date' => $jobPosting->expires_at->toFormattedDateString(),
        ]));
    }

    public function duplicate(int $jobPostingId, DuplicateJobPosting $duplicateJobPosting): void
    {
        $jobPosting = $this->find($jobPostingId);
        $this->authorize('duplicate', $jobPosting);

        $copy = $duplicateJobPosting($jobPosting->load(['categories', 'skills', 'screeningQuestions']), auth()->user());

        $this->redirectRoute('employer.jobs.edit', [
            'company' => $this->company,
            'jobPosting' => $copy,
        ], navigate: true);
    }

    private function find(int $jobPostingId): JobPosting
    {
        return $this->company->jobPostings()->findOrFail($jobPostingId);
    }
}; ?>

<div class="mx-auto flex max-w-5xl flex-col gap-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">{{ __('Job postings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Everything :company has posted.', ['company' => $this->company->name]) }}</flux:text>
        </div>

        @can('create', [\App\Models\JobPosting::class, $this->company])
            <flux:button variant="primary" icon="plus" :href="route('employer.jobs.create', $this->company)" wire:navigate>
                {{ __('Post a job') }}
            </flux:button>
        @endcan
    </div>

    <flux:radio.group wire:model.live="filter" variant="segmented" :label="__('Filter by status')">
        <flux:radio value="all" :label="__('All')" />
        @foreach (AvailabilityStatus::cases() as $status)
            <flux:radio value="{{ $status->value }}" :label="$status->label()" />
        @endforeach
    </flux:radio.group>

    @if ($this->jobPostings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __('Nothing here yet.') }}</flux:text>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-sm">
                <caption class="sr-only">{{ __('Job postings') }}</caption>
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Job') }}</th>
                        <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th scope="col" class="px-5 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Applications') }}</th>
                        <th scope="col" class="px-5 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->jobPostings as $jobPosting)
                        <tr wire:key="job-{{ $jobPosting->id }}">
                            <td class="px-5 py-4">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $jobPosting->title }}</div>
                                <div class="text-zinc-500 dark:text-zinc-500">
                                    {{ __('Closes :date', ['date' => $jobPosting->expires_at->toFormattedDateString()]) }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <flux:badge :color="$jobPosting->availability_status === AvailabilityStatus::Active ? 'green' : 'zinc'">
                                    {{ $jobPosting->availability_status->label() }}
                                </flux:badge>

                                @if ($jobPosting->moderation_status !== \App\Enums\ModerationStatus::Approved)
                                    <flux:badge color="yellow">{{ $jobPosting->moderation_status->label() }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-end tabular-nums">
                                <a
                                    href="{{ route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $jobPosting]) }}"
                                    class="text-zinc-700 hover:text-brand-700 hover:underline dark:text-zinc-300 dark:hover:text-brand-400"
                                    wire:navigate
                                >
                                    {{ $jobPosting->applications_count }}
                                    @if ($jobPosting->new_applications_count > 0)
                                        <span class="text-brand-700 dark:text-brand-400">({{ $jobPosting->new_applications_count }} {{ __('new') }})</span>
                                    @endif
                                </a>
                            </td>
                            <td class="px-5 py-4 text-end">
                                @can('update', $jobPosting)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :title', ['title' => $jobPosting->title])" />

                                        <flux:menu>
                                            <flux:menu.item icon="pencil" :href="route('employer.jobs.edit', ['company' => $this->company, 'jobPosting' => $jobPosting])" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $jobPosting->id }})">
                                                {{ __('Duplicate') }}
                                            </flux:menu.item>

                                            <flux:menu.item icon="calendar" wire:click="extend({{ $jobPosting->id }})">
                                                {{ __('Extend by a month') }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            @if ($jobPosting->availability_status === AvailabilityStatus::Active)
                                                <flux:menu.item
                                                    variant="danger"
                                                    icon="x-circle"
                                                    wire:click="close({{ $jobPosting->id }})"
                                                    wire:confirm="{{ __('Close this posting? Candidates will no longer be able to apply.') }}"
                                                >
                                                    {{ __('Close') }}
                                                </flux:menu.item>
                                            @else
                                                <flux:menu.item icon="arrow-path" wire:click="reopen({{ $jobPosting->id }})">
                                                    {{ __('Reopen') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:text size="sm" class="text-zinc-400">&mdash;</flux:text>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
