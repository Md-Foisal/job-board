@php
    $needsYou = $undoable->isNotEmpty()
        || $newApplications->isNotEmpty()
        || $needsChanges->isNotEmpty()
        || $closingSoon->isNotEmpty()
        || $documentsRequest !== null
        || $draftCount > 0
        || $pendingInvitationCount > 0;
    $namedNew = $newApplications->take(\App\Http\Controllers\EmployerDashboardController::NEW_APPLICATION_ROWS);
    $moreNew = $newApplications->count() - $namedNew->count();
@endphp

<x-layouts::employer :company="$company" :title="$company->name">
    <x-page>
        <x-page-header :title="$company->name">
            <x-slot:media>
                <x-company-logo :company="$company" />
            </x-slot:media>
            <x-slot:status>
                <x-verification-status :company="$company" />
            </x-slot:status>
            <x-slot:actions>
                {{-- On a phone the two secondary actions keep only their icons,
                     so all three fit on one row; the name stays for screen
                     readers. --}}
                <flux:button variant="ghost" icon="eye" :href="route('companies.show', $company)" :aria-label="__('View company page')">
                    <span class="hidden sm:inline">{{ __('View company page') }}</span>
                </flux:button>
                <flux:button icon="chart-bar" :href="route('employer.analytics', $company)" wire:navigate :aria-label="__('Analytics')">
                    <span class="hidden sm:inline">{{ __('Analytics') }}</span>
                </flux:button>
                @can('create', [\App\Models\JobPosting::class, $company])
                    <flux:button variant="primary" class="btn-sunset" icon="plus" :href="route('employer.jobs.create', $company)" wire:navigate>
                        {{ __('Post a job') }}
                    </flux:button>
                @endcan
            </x-slot:actions>
        </x-page-header>

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

        {{-- Needs you: each line one piece of work waiting on this person,
             and the page where they do it. Only what they can act on. --}}
        <x-card padding="none" class="overflow-hidden">
            <div class="px-5 pt-5 pb-4 sm:px-6">
                <flux:heading size="lg" level="2">{{ __('Needs you') }}</flux:heading>
            </div>

            @if ($needsYou)
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($undoable as $application)
                        <x-task-row :href="route('employer.applications.show', ['company' => $company, 'application' => $application])" icon="arrow-uturn-left">
                            {{ __('You can still undo the decision on :name', ['name' => $application->candidateProfile->user->name]) }}
                            <x-slot:detail>
                                {{ __(':outcome for :job · :time left before they are told', [
                                    'outcome' => __($application->outcome_status->label()),
                                    'job' => $application->jobPosting->title,
                                    'time' => $application->decided_at->copy()->addMinutes(\App\Models\Application::UNDO_MINUTES)->diffForHumans(null, true),
                                ]) }}
                            </x-slot:detail>
                        </x-task-row>
                    @endforeach

                    @foreach ($namedNew as $jobPosting)
                        <x-task-row :href="route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $jobPosting, 'stage' => \App\Enums\ApplicationStage::New->value])" icon="inbox">
                            {{ trans_choice('{1} :count new application for :job|[2,*] :count new applications for :job', $jobPosting->stage_new_count, ['count' => $jobPosting->stage_new_count, 'job' => $jobPosting->title]) }}
                            <x-slot:detail>
                                {{ trans_choice('{1} Waiting :time|[2,*] The oldest has waited :time', $jobPosting->stage_new_count, ['time' => $jobPosting->oldest_new_at->diffForHumans(null, true)]) }}
                            </x-slot:detail>
                        </x-task-row>
                    @endforeach

                    @if ($moreNew > 0)
                        <x-task-row :href="route('employer.jobs.index', $company)" icon="briefcase">
                            {{ trans_choice('{1} :count more job has new applications|[2,*] :count more jobs have new applications', $moreNew, ['count' => $moreNew]) }}
                        </x-task-row>
                    @endif

                    @foreach ($needsChanges as $jobPosting)
                        <x-task-row :href="route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $jobPosting])" icon="pencil-square">
                            {{ __(':job needs changes before it goes live', ['job' => $jobPosting->title]) }}
                            <x-slot:detail class="line-clamp-2">
                                {{ $jobPosting->latestRejection?->reason ?: __('Our team sent it back. Edit it and save to send it back for review.') }}
                            </x-slot:detail>
                        </x-task-row>
                    @endforeach

                    @foreach ($closingSoon as $jobPosting)
                        <x-task-row :href="route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $jobPosting]).'#closing-date'" icon="clock">
                            {{ __(':job closes in :time', ['job' => $jobPosting->title, 'time' => $jobPosting->expires_at->diffForHumans(null, true)]) }}
                            <x-slot:detail>{{ __('Move the closing date if you are still hiring.') }}</x-slot:detail>
                        </x-task-row>
                    @endforeach

                    @if ($documentsRequest !== null)
                        <x-task-row :href="route('employer.company.edit', $company)" icon="document-text">
                            {{ __('Our team asked for documents') }}
                            <x-slot:detail>{{ __('Needed before :company can be verified.', ['company' => $company->name]) }}</x-slot:detail>
                        </x-task-row>
                    @endif

                    @if ($draftCount > 0)
                        <x-task-row :href="route('employer.jobs.index', ['company' => $company, 'status' => \App\Enums\AvailabilityStatus::Draft->value])" icon="document">
                            {{ trans_choice('{1} :count draft is not published yet|[2,*] :count drafts are not published yet', $draftCount, ['count' => $draftCount]) }}
                            <x-slot:detail>{{ __('Candidates see a posting once it is published.') }}</x-slot:detail>
                        </x-task-row>
                    @endif

                    @if ($pendingInvitationCount > 0)
                        <x-task-row :href="route('employer.team.index', $company)" icon="envelope">
                            {{ trans_choice('{1} :count invitation is not accepted yet|[2,*] :count invitations are not accepted yet', $pendingInvitationCount, ['count' => $pendingInvitationCount]) }}
                            <x-slot:detail>{{ __('Remind them, or revoke it from the team page.') }}</x-slot:detail>
                        </x-task-row>
                    @endif
                </ul>
            @else
                <div class="px-5 pb-5 sm:px-6">
                    <x-empty-state icon="check-circle" :level="3" :heading="__('Nothing needs you right now')">
                        {{ $canManage
                            ? __('New applications, a posting sent back by our team or one about to close would show here.')
                            : __('New applications to look at would show here.') }}
                    </x-empty-state>
                </div>
            @endif
        </x-card>

        {{-- Where each open job stands: its open applications by stage,
             each count a link to that stage of the list. --}}
        <section aria-labelledby="open-jobs-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg" level="2" id="open-jobs-heading">{{ __('Open jobs') }}</flux:heading>
                @if ($hasPostings)
                    <flux:link :href="route('employer.jobs.index', $company)" wire:navigate class="text-sm">{{ __('All job postings') }}</flux:link>
                @endif
            </div>

            @if ($openJobs->isEmpty())
                <x-empty-state icon="briefcase" class="mt-4" :heading="$hasPostings ? __('No open jobs') : __('No job postings yet')">
                    {{ $hasPostings
                        ? __('Your postings are drafts, closed or past their closing date. Open jobs show here with their applicants by stage.')
                        : __('Post a job and it shows up here, with its applicants by stage.') }}
                    @can('create', [\App\Models\JobPosting::class, $company])
                        <x-slot:actions>
                            <flux:button :href="route('employer.jobs.create', $company)" variant="primary" size="sm" wire:navigate>{{ __('Post a job') }}</flux:button>
                        </x-slot:actions>
                    @endcan
                </x-empty-state>
            @else
                <x-card padding="none" class="mt-4 overflow-hidden">
                    {{-- The stage names head the columns on a wide screen and
                         sit under each number on a phone. --}}
                    <div class="hidden border-b border-line bg-surface px-5 py-3 text-sm font-medium text-ink-muted sm:grid sm:grid-cols-[minmax(0,1fr)_repeat(4,6rem)] sm:gap-4 sm:px-6" aria-hidden="true">
                        <span>{{ __('Job') }}</span>
                        @foreach ($stages as $stage)
                            <span class="text-end">{{ __($stage->label()) }}</span>
                        @endforeach
                    </div>

                    <ul class="divide-y divide-line">
                        @foreach ($openJobs as $jobPosting)
                            @php
                                $state = \App\Enums\PostingState::of($jobPosting);
                            @endphp
                            <li class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_repeat(4,6rem)] sm:items-center sm:gap-4 sm:px-6">
                                <div class="min-w-0">
                                    <a
                                        href="{{ route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $jobPosting]) }}"
                                        class="font-medium text-ink hover:text-sunset-small"
                                        wire:navigate
                                    >{{ $jobPosting->title }}</a>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-muted">
                                        {{-- Live is what an open job is meant to be; only
                                             the exceptions earn a pill. --}}
                                        @if ($state !== \App\Enums\PostingState::Live)
                                            <x-posting-status :job-posting="$jobPosting" :detailed="false" />
                                        @endif
                                        <span>{{ __('Closes :date', ['date' => \App\Support\ClosingDate::day($jobPosting, $company)->format(\App\Support\DateFormat::DAY)]) }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 gap-1 sm:contents">
                                    @foreach ($stages as $stage)
                                        @php
                                            $count = $jobPosting->{'stage_'.$stage->value.'_count'};
                                        @endphp
                                        <a
                                            href="{{ route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $jobPosting, 'stage' => $stage->value]) }}"
                                            wire:navigate
                                            class="group flex min-w-0 flex-col items-center rounded-control py-1.5 transition hover:bg-surface sm:items-end sm:px-2"
                                            aria-label="{{ trans_choice('{0} No applications at :stage for :job|{1} :count application at :stage for :job|[2,*] :count applications at :stage for :job', $count, ['count' => $count, 'stage' => __($stage->label()), 'job' => $jobPosting->title]) }}"
                                        >
                                            <span @class([
                                                'font-display text-xl font-semibold tabular-nums',
                                                'text-ink group-hover:text-sunset-small' => $count > 0,
                                                'text-ink-muted' => $count === 0,
                                            ])>{{ $count }}</span>
                                            <span class="max-w-full truncate text-meta text-ink-muted sm:hidden">{{ __($stage->label()) }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </section>
    </x-page>
</x-layouts::employer>
