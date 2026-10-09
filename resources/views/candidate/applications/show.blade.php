@php
    $jobPosting = $application->jobPosting;
    $company = $jobPosting->company;
    // The job page answers "not found" once a posting is closed or hidden,
    // so it is only linked while there is a page to open.
    $jobIsPublic = $jobPosting->isPubliclyVisible();
    $resume = $application->resumeDocument;
@endphp

<x-layouts::app :title="$jobPosting->title">
    <x-page>
        <x-page-header :back="route('candidate.applications.index')" :back-label="__('Applications')">
            {{-- Left out on a phone, where the title needs the width. --}}
            <x-slot:media>
                <span class="hidden sm:block"><x-company-logo :company="$company" size="lg" /></span>
            </x-slot:media>

            <x-slot:title>
                @if ($jobIsPublic)
                    <a href="{{ route('jobs.show', $jobPosting) }}" class="hover:text-sunset-small" wire:navigate>{{ $jobPosting->title }}</a>
                @else
                    {{ $jobPosting->title }}
                @endif
            </x-slot:title>

            <x-slot:status>
                <x-application-status :application="$application" for-candidate />
            </x-slot:status>

            @if ($company->isPubliclyVisible())
                <a href="{{ route('companies.show', $company) }}" class="hover:text-sunset-small" wire:navigate>{{ $company->name }}</a>
            @else
                {{ $company->name }}
            @endif
            &middot;
            {{ __('Applied on :date', ['date' => \App\Support\LocalTime::of($application->created_at)->format(\App\Support\DateFormat::DAY)]) }}
            @unless ($jobPosting->isOpen())
                &middot; {{ __('Job closed') }}
            @endunless

            @can('withdraw', $application)
                <x-slot:actions>
                    <flux:modal.trigger name="withdraw-application">
                        <flux:button variant="ghost" icon="x-circle">{{ __('Withdraw') }}</flux:button>
                    </flux:modal.trigger>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                <x-card>
                    <flux:heading size="lg" level="2" class="mb-6">{{ __('History') }}</flux:heading>

                    {{-- The timeline always has at least one entry: the
                         application itself. Employer-side entries name the
                         company, never the individual staff member who made
                         the change. --}}
                    <ol class="relative border-s border-line">
                        <x-timeline-item :label="__('You applied')" :at="$application->created_at" highlight />

                        @foreach ($application->eventsForCandidate() as $event)
                            <x-timeline-item :label="\App\Support\CandidateTimeline::line($event, $application, auth()->id())" :at="$event->created_at" />
                        @endforeach
                    </ol>
                </x-card>

                <livewire:company-review :application="$application" />
            </div>

            {{-- What went to the company, as it went: the application keeps
                 its own copy, so this stays true after the CV leaves the
                 library or the profile changes. --}}
            <x-card as="section" aria-labelledby="sent-heading" class="flex flex-col gap-5">
                <flux:heading size="lg" level="2" id="sent-heading">{{ __('What you sent') }}</flux:heading>

                <div>
                    <p class="text-meta font-medium text-ink-muted">{{ __('CV') }}</p>
                    @if ($resume)
                        <a href="{{ route('candidate.applications.resume', $application) }}" target="_blank" rel="noopener" class="group mt-1.5 flex items-center gap-3 rounded-control border border-line p-3 transition hover:bg-surface">
                            <flux:icon.document-text class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink group-hover:text-sunset-small">{{ $resume->original_filename }}</span>
                            <flux:icon.arrow-top-right-on-square variant="micro" class="shrink-0 text-ink-muted" aria-hidden="true" />
                        </a>
                    @else
                        <p class="mt-1 text-sm text-ink-muted">{{ __('No CV was attached.') }}</p>
                    @endif
                </div>

                <div>
                    <p class="text-meta font-medium text-ink-muted">{{ __('Cover letter') }}</p>
                    @if (filled($application->cover_letter))
                        <div class="prose-content mt-1.5 text-sm">{!! $application->cover_letter !!}</div>
                    @else
                        <p class="mt-1 text-sm text-ink-muted">{{ __('None — it was optional.') }}</p>
                    @endif
                </div>

                @if ($application->screeningAnswers->isNotEmpty())
                    <dl class="flex flex-col gap-4 text-sm">
                        @foreach ($application->screeningAnswers as $answer)
                            <div>
                                <dt class="font-medium text-ink">{{ $answer->screeningQuestion?->question_text }}</dt>
                                <dd class="mt-1 whitespace-pre-line text-ink-soft">{{ $answer->answer_text }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-card>
        </div>

        @can('withdraw', $application)
            {{-- A plain form post, not Livewire: withdrawing is one isolated
                 action on an otherwise static page. The modal is Flux's
                 name-based one so an irreversible action still asks first. --}}
            <flux:modal name="withdraw-application" class="max-w-md">
                <form method="POST" action="{{ route('candidate.applications.withdraw', $application) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <flux:heading size="lg">{{ __('Withdraw this application?') }}</flux:heading>
                        <flux:subheading>
                            {{ __(':company stops considering you for this role. You stay in the running for every other job you applied to, and you cannot apply to this one again.', ['company' => $company->name]) }}
                        </flux:subheading>
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="danger">{{ __('Withdraw') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endcan
    </x-page>
</x-layouts::app>
