<x-layouts::app :title="$application->jobPosting->title">
    <x-page>
        <x-page-header :back="route('candidate.applications.index')" :back-label="__('Applications')">
            <x-slot:title>
                <a href="{{ route('jobs.show', $application->jobPosting) }}" class="hover:text-sunset-small" wire:navigate>{{ $application->jobPosting->title }}</a>
            </x-slot:title>

            <x-slot:status>
                <x-application-status :application="$application" for-candidate />
            </x-slot:status>

            <a href="{{ route('companies.show', $application->jobPosting->company) }}" class="hover:text-sunset-small" wire:navigate>{{ $application->jobPosting->company->name }}</a>
            &middot;
            {{ __('Applied') }} {{ $application->created_at->diffForHumans() }}
        </x-page-header>

        {{-- One reading column until the page gets its own layout: a
             timeline stretched across the full width is hard to follow. --}}
        <div class="max-w-3xl">
            <flux:heading size="lg" level="2">{{ __('History') }}</flux:heading>
            <flux:subheading class="mb-6">
                {{ __('Everything that has happened to this application, newest at the bottom.') }}
            </flux:subheading>

            {{-- The timeline always has at least one entry: the application
                 itself. Employer-side entries name the company, never the
                 individual staff member who made the change -- the
                 candidate has no business knowing which
                 person opened their CV). --}}
            <ol class="relative border-s border-line">
                <x-timeline-item :label="__('You applied')" :at="$application->created_at" highlight />

                @foreach ($application->eventsForCandidate() as $event)
                    @php
                        $company = $application->jobPosting->company->name;
                        $byCandidate = $event->changed_by_id === auth()->id();

                        if ($event->to_stage !== null) {
                            $label = \App\Enums\ApplicationStage::tryFrom($event->to_stage)?->label() ?? $event->to_stage;
                            $line = __(':company moved your application to :stage', ['company' => $company, 'stage' => $label]);
                        } else {
                            $outcome = \App\Enums\ApplicationOutcomeStatus::tryFrom($event->to_outcome_status);
                            $label = $outcome?->label() ?? $event->to_outcome_status;

                            $line = $byCandidate
                                ? __('You withdrew this application')
                                : __(':company marked this application as :outcome', ['company' => $company, 'outcome' => $label]);
                        }
                    @endphp

                    <x-timeline-item :label="$line" :at="$event->created_at" />
                @endforeach
            </ol>

            <livewire:company-review :application="$application" />

            @can('withdraw', $application)
                <flux:separator variant="subtle" class="my-8" />

                <x-card class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium text-ink">{{ __('Withdraw this application') }}</p>
                        <p class="text-sm text-ink-muted">
                            {{ __('The employer stops considering you for this role. This cannot be undone.') }}
                        </p>
                    </div>

                    <flux:modal.trigger name="withdraw-application">
                        <flux:button variant="danger" size="sm">{{ __('Withdraw') }}</flux:button>
                    </flux:modal.trigger>
                </x-card>

                {{-- A plain form post, not Livewire: withdrawing is one
                     isolated action on an otherwise static page, which has no
                     other reason to be a Livewire component. The modal
                     is Flux's name-based one so an irreversible action
                     still asks first. --}}
                <flux:modal name="withdraw-application" class="max-w-md">
                    <form method="POST" action="{{ route('candidate.applications.withdraw', $application) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <flux:heading size="lg">{{ __('Withdraw this application?') }}</flux:heading>
                            <flux:subheading>
                                {{ __('You will stay in the running for every other job you have applied to. You cannot re-apply to this one.') }}
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
        </div>
    </x-page>
</x-layouts::app>
