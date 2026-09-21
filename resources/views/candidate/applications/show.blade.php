<x-layouts::app :title="$application->jobPosting->title">
    <div class="mx-auto max-w-3xl">
        <x-breadcrumb :items="[
            ['label' => __('My Applications'), 'url' => route('candidate.applications.index')],
            ['label' => $application->jobPosting->title],
        ]" />

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">
                    <a href="{{ route('jobs.show', $application->jobPosting) }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>
                        {{ $application->jobPosting->title }}
                    </a>
                </flux:heading>
                <flux:subheading size="lg">
                    <a href="{{ route('companies.show', $application->jobPosting->company) }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>
                        {{ $application->jobPosting->company->name }}
                    </a>
                    &middot;
                    {{ __('Applied') }} {{ $application->created_at->diffForHumans() }}
                </flux:subheading>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-application-status :application="$application" />
            </div>
        </div>

        <flux:separator variant="subtle" class="my-6" />

        <flux:heading size="lg" level="2">{{ __('History') }}</flux:heading>
        <flux:subheading class="mb-6">
            {{ __('Everything that has happened to this application, newest at the bottom.') }}
        </flux:subheading>

        {{-- The timeline always has at least one entry: the application
             itself. Employer-side entries name the company, never the
             individual staff member who made the change -- the
             candidate has no business knowing which
             person opened their CV). --}}
        <ol class="relative border-s border-zinc-200 dark:border-zinc-800">
            <x-timeline-item :label="__('You applied')" :at="$application->created_at" highlight />

            @foreach ($application->events as $event)
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

        @can('withdraw', $application)
            <flux:separator variant="subtle" class="my-8" />

            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <div class="min-w-0">
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Withdraw this application') }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-500">
                        {{ __('The employer stops considering you for this role. This cannot be undone.') }}
                    </p>
                </div>

                <flux:modal.trigger name="withdraw-application">
                    <flux:button variant="danger" size="sm">{{ __('Withdraw') }}</flux:button>
                </flux:modal.trigger>
            </div>

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
</x-layouts::app>
