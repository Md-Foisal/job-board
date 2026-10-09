<?php

use App\Actions\DuplicateJobPosting;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\ReportStatus;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\ClosingDate;
use App\Support\SubmissionLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Job postings')] class extends Component {
    public Company $company;

    /**
     * The tab, in the address so a tab can be bookmarked and the
     * dashboard can link straight to the drafts.
     */
    #[Url(as: 'status', except: 'all')]
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
                'applications as new_applications_count' => fn ($query) => $query
                    ->where('stage', ApplicationStage::New)
                    ->where('outcome_status', ApplicationOutcomeStatus::Active),
                'reports as open_reporters_count' => fn ($query) => $query
                    ->where('review_status', ReportStatus::Pending)
                    ->select(DB::raw('count(distinct reporter_id)')),
            ])
            ->with(['latestRejection', 'postedBy:id,name'])
            ->latest()
            ->latest('id')
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

        // Only a posting that has been out and come down goes back up this
        // way. A draft has never been submitted, so it is published from
        // the form, where it goes through review.
        if (! in_array($jobPosting->availability_status, [AvailabilityStatus::Closed, AvailabilityStatus::Expired], true)) {
            return;
        }

        // Reopening something already past its date would put it straight
        // back into the expired pile, so the date moves with it.
        if ($jobPosting->expires_at->isPast()) {
            $jobPosting->expires_at = ClosingDate::monthAfter($this->company);
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

        $jobPosting->expires_at = ClosingDate::monthAfter($this->company, $jobPosting->expires_at);

        if ($jobPosting->availability_status === AvailabilityStatus::Expired) {
            $jobPosting->availability_status = AvailabilityStatus::Active;
        }

        $jobPosting->save();

        unset($this->jobPostings);
        Flux::toast(variant: 'success', text: __('Closing date moved to :date.', [
            'date' => ClosingDate::day($jobPosting, $this->company)->format(\App\Support\DateFormat::DAY),
        ]));
    }

    public function duplicate(int $jobPostingId, DuplicateJobPosting $duplicateJobPosting): void
    {
        $jobPosting = $this->find($jobPostingId);
        $this->authorize('duplicate', $jobPosting);

        // A copy is a new posting, so it draws on the same daily allowance
        // as the form; otherwise duplicating would be a way round it.
        $limitKey = SubmissionLimits::jobPostingKey($this->company);

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::JOB_POSTINGS_PER_DAY)) {
            $message = SubmissionLimits::jobPostingLimitMessage($this->company);

            Flux::toast(variant: 'warning', duration: 10000, heading: $message['heading'], text: $message['text']);

            return;
        }

        $copy = $duplicateJobPosting($jobPosting->load(['categories', 'skills', 'screeningQuestions']), auth()->user());

        RateLimiter::hit($limitKey, 86400);

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

@php
    // Tab order follows a posting's life; "Open" rather than the stored
    // "active", the word the rest of the page uses for a posting that
    // takes applications.
    $tabs = [
        'all' => __('All'),
        AvailabilityStatus::Active->value => __('Open'),
        AvailabilityStatus::Draft->value => __('Drafts'),
        AvailabilityStatus::Closed->value => __('Closed'),
        AvailabilityStatus::Expired->value => __('Expired'),
    ];
    $emptyHeadings = [
        AvailabilityStatus::Active->value => __('No open postings'),
        AvailabilityStatus::Draft->value => __('No drafts'),
        AvailabilityStatus::Closed->value => __('No closed postings'),
        AvailabilityStatus::Expired->value => __('No expired postings'),
    ];
@endphp

<x-page>
    <x-page-header :title="__('Job postings')">
        @can('create', [\App\Models\JobPosting::class, $this->company])
            <x-slot:actions>
                <flux:button variant="primary" class="btn-sunset" icon="plus" :href="route('employer.jobs.create', $this->company)" wire:navigate>
                    {{ __('Post a job') }}
                </flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-tab-nav :label="__('Job postings by status')">
        @foreach ($tabs as $value => $label)
            <x-tab-nav.item
                :href="route('employer.jobs.index', $value === 'all' ? $this->company : ['company' => $this->company, 'status' => $value])"
                :current="$filter === $value"
            >{{ $label }}</x-tab-nav.item>
        @endforeach
    </x-tab-nav>

    @if ($this->jobPostings->isEmpty())
        @if ($this->filter === 'all')
            <x-empty-state icon="briefcase" :heading="__('No job postings yet')">
                {{ __('Post a job and it shows up here, with how many people have applied.') }}
                @can('create', [\App\Models\JobPosting::class, $this->company])
                    <x-slot:actions>
                        <flux:button :href="route('employer.jobs.create', $this->company)" variant="primary" size="sm" wire:navigate>{{ __('Post a job') }}</flux:button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-empty-state icon="funnel" :heading="$emptyHeadings[$this->filter] ?? __('No postings with this status')" :action-href="route('employer.jobs.index', $this->company)" :action-label="__('Show all postings')" />
        @endif
    @else
        <x-card padding="none" class="overflow-hidden">
            <ul class="divide-y divide-line">
                @foreach ($this->jobPostings as $jobPosting)
                    @php
                        $state = \App\Enums\PostingState::of($jobPosting);
                        $applicationsUrl = route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $jobPosting]);
                    @endphp
                    <li wire:key="job-{{ $jobPosting->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:gap-6 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <a href="{{ $applicationsUrl }}" class="font-medium text-ink hover:text-sunset-small" wire:navigate>{{ $jobPosting->title }}</a>

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                                <x-posting-status :job-posting="$jobPosting" :detailed="false" />
                                <span>
                                    @switch ($state)
                                        @case (\App\Enums\PostingState::Draft)
                                            {{ __('Edited :time', ['time' => $jobPosting->updated_at->diffForHumans()]) }}
                                            @break
                                        @case (\App\Enums\PostingState::Closed)
                                            {{ $jobPosting->published_at ? __('Posted :date', ['date' => \App\Support\LocalTime::of($jobPosting->published_at)->format(\App\Support\DateFormat::DAY)]) : '' }}
                                            @break
                                        @case (\App\Enums\PostingState::Expired)
                                            {{ __('Closed :date', ['date' => ClosingDate::day($jobPosting, $company)->format(\App\Support\DateFormat::DAY)]) }}
                                            @break
                                        @default
                                            {{ __('Closes :date', ['date' => ClosingDate::day($jobPosting, $company)->format(\App\Support\DateFormat::DAY)]) }}
                                    @endswitch
                                </span>
                                @if ($jobPosting->postedBy)
                                    <span aria-hidden="true">&middot;</span>
                                    <span>{{ __('by :name', ['name' => $jobPosting->postedBy->name]) }}</span>
                                @endif
                            </div>

                            {{-- The reason a posting was sent back, or why it is out of
                                 search, where the posting is listed. --}}
                            @if (in_array($state, [\App\Enums\PostingState::NeedsChanges, \App\Enums\PostingState::HiddenForReview], true))
                                <x-posting-status :job-posting="$jobPosting" :badge="false" />
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <a href="{{ $applicationsUrl }}" class="text-sm text-ink-soft hover:text-sunset-small" wire:navigate>
                                <span class="font-medium tabular-nums text-ink">{{ $jobPosting->applications_count }}</span>
                                {{ trans_choice('applicant|applicants', $jobPosting->applications_count) }}
                                @if ($jobPosting->new_applications_count > 0)
                                    <span class="text-sunset-small">({{ $jobPosting->new_applications_count }} {{ __('new') }})</span>
                                @endif
                            </a>

                            <div class="flex items-center gap-1">
                                @can('update', $jobPosting)
                                    <flux:button size="sm" icon="pencil-square" :href="route('employer.jobs.edit', ['company' => $this->company, 'jobPosting' => $jobPosting])" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:button>
                                @endcan

                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More for :title', ['title' => $jobPosting->title])" />

                                    <flux:menu>
                                        {{-- The company's own people can open the job page in
                                             every state; it says there who can see it. --}}
                                        <flux:menu.item icon="eye" :href="route('jobs.show', $jobPosting)">
                                            {{ __('View job page') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            icon="chart-bar"
                                            :href="route('employer.analytics', ['company' => $this->company, 'job' => $jobPosting->slug])"
                                            :aria-label="__('Stats for :title', ['title' => $jobPosting->title])"
                                            wire:navigate
                                        >
                                            {{ __('Stats') }}
                                        </flux:menu.item>

                                        @can('update', $jobPosting)
                                            <flux:menu.separator />

                                            <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $jobPosting->id }})">
                                                {{ __('Duplicate') }}
                                            </flux:menu.item>

                                            @if ($state !== \App\Enums\PostingState::Draft)
                                                <flux:menu.item icon="calendar" wire:click="extend({{ $jobPosting->id }})">
                                                    {{ __('Extend by a month') }}
                                                </flux:menu.item>
                                            @endif

                                            @if ($jobPosting->availability_status === AvailabilityStatus::Active)
                                                <flux:menu.separator />

                                                <flux:menu.item
                                                    variant="danger"
                                                    icon="x-circle"
                                                    wire:click="close({{ $jobPosting->id }})"
                                                    wire:confirm="{{ __('Close this posting? Candidates will no longer be able to apply.') }}"
                                                >
                                                    {{ __('Close') }}
                                                </flux:menu.item>
                                            @elseif ($jobPosting->availability_status !== AvailabilityStatus::Draft)
                                                <flux:menu.item icon="arrow-path" wire:click="reopen({{ $jobPosting->id }})">
                                                    {{ __('Reopen') }}
                                                </flux:menu.item>
                                            @endif
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-page>
