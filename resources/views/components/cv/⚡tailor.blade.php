<?php

use App\Support\CvData;
use App\Support\CvTailoring;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/*
 * Aiming one saved CV at a job: the job on the board it was made for, or
 * a job elsewhere whose description is pasted in. Shows which of the
 * job's skills the CV already names and which it does not, by our own
 * reading (CvTailoring), so the candidate can add the ones they really
 * have. The CV then puts the projects closest to the job first.
 */
new class extends Component {
    #[Locked]
    public int $versionId;

    public string $jobDescription = '';

    public function mount(): void
    {
        $this->jobDescription = (string) $this->version()->job_description;
    }

    public function updatedJobDescription(): void
    {
        $this->validate(['jobDescription' => ['string', 'max:10000']], [], ['jobDescription' => __('job description')]);

        $this->version()->update(['job_description' => trim($this->jobDescription) === '' ? null : $this->jobDescription]);
        unset($this->tailoring, $this->coverage);
        $this->dispatch('cv-updated');
    }

    /**
     * The CV is no longer aimed at the board's job; a pasted description
     * can take its place.
     */
    public function detachJob(): void
    {
        $this->version()->update(['job_posting_id' => null]);
        unset($this->tailoring, $this->coverage);
        $this->dispatch('cv-updated');
    }

    #[On('profile-updated')]
    #[On('cv-updated')]
    public function refresh(): void
    {
        auth()->user()->unsetRelation('candidateProfile');
        unset($this->tailoring, $this->coverage);
    }

    #[Computed]
    public function tailoring(): CvTailoring
    {
        return CvTailoring::for($this->version());
    }

    /**
     * @return array{covered: list<string>, missing: list<string>, percent: ?int}
     */
    #[Computed]
    public function coverage(): array
    {
        $version = $this->version();

        return $this->tailoring->coverage(CvData::fromProfile($version->candidateProfile, $version, $this->tailoring->keywords));
    }

    private function version()
    {
        return auth()->user()->candidateProfile->cvVersions()->with('jobPosting.company')->findOrFail($this->versionId);
    }
}; ?>

@php
    $tailoring = $this->tailoring;
    $coverage = $this->coverage;
    $job = $tailoring->job;
@endphp

<x-profile.section id="cv-tailor" :heading="__('Aim this CV at a job')">
    <div class="space-y-5 border-t border-line px-5 py-5 sm:px-6">
        @if ($job)
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-ink-muted">{{ __('Made for') }}</p>
                    <a href="{{ route('jobs.show', $job) }}" class="font-medium text-ink hover:underline" wire:navigate>{{ $job->title }}</a>
                    <p class="text-sm text-ink-soft">{{ $job->company->name }}</p>
                </div>
                <flux:button wire:click="detachJob" variant="ghost" size="sm">{{ __('Not for this job any more') }}</flux:button>
            </div>
        @else
            <flux:text size="sm">{{ __('Paste the description of a job you are applying for elsewhere, or open a job on this board and choose "Tailor my CV for this job".') }}</flux:text>
            <flux:textarea wire:model.blur.live="jobDescription" :label="__('Job description')" rows="5" maxlength="10000" :placeholder="__('Paste the job ad here')" />
        @endif

        @if ($tailoring->hasTarget())
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading>{{ __('The job\'s skills on your CV') }}</flux:heading>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-300' => $coverage['percent'] >= \App\Support\CvTailoring::GOOD_COVERAGE,
                        'bg-warning-50 text-warning-700 dark:bg-warning-950 dark:text-warning-300' => $coverage['percent'] < \App\Support\CvTailoring::GOOD_COVERAGE,
                    ])>{{ __(':percent% covered', ['percent' => $coverage['percent']]) }}</span>
                </div>

                @if ($coverage['covered'] !== [])
                    <h3 class="mt-3 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('On your CV') }}</h3>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($coverage['covered'] as $keyword)
                            <li><x-chip variant="matched">{{ $keyword }}</x-chip></li>
                        @endforeach
                    </ul>
                @endif

                @if ($coverage['missing'] !== [])
                    <h3 class="mt-3 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('Not on your CV') }}</h3>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($coverage['missing'] as $keyword)
                            <li>
                                <x-chip variant="missing" :required="in_array($keyword, $tailoring->required, true)">
                                    {{ $keyword }}
                                    @if (in_array($keyword, $tailoring->required, true))
                                        <span class="font-normal">{{ __('(required)') }}</span>
                                    @endif
                                </x-chip>
                            </li>
                        @endforeach
                    </ul>
                    <flux:text size="sm" class="mt-3">
                        {{ __('Add the ones you really have to your skills, or mention them in a role or project where you used them. Never list a skill you could not talk about in an interview.') }}
                    </flux:text>
                @endif

                <flux:text size="sm" class="mt-3 text-ink-muted">{{ __('On this CV, projects that mention these skills come first. Roles stay newest first, as recruiters expect.') }}</flux:text>
            </div>
        @elseif ($job || filled($jobDescription))
            <flux:text size="sm" class="text-ink-muted">{{ __('We found no skills from our list in this job, so there is nothing to compare yet.') }}</flux:text>
        @endif
    </div>
</x-profile.section>
