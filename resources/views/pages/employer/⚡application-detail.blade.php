<?php

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Services\MatchScoreCalculator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Application')] class extends Component {
    public Company $company;

    public Application $application;

    public string $stage = '';

    public string $newNote = '';

    public ?int $editingNoteId = null;

    public string $editingNoteBody = '';

    public function mount(Company $company, Application $application): void
    {
        // Laravel scopes a child binding to its parent only when the child
        // carries a custom key, which {application} does not -- so this is
        // checked by hand. Without it, somebody who works at two companies
        // could open one company's URL and be shown the other's applicant:
        // not a leak, since they are entitled to both, but a page whose
        // heading and contents disagree about whose it is.
        abort_unless($application->jobPosting->company_id === $company->id, 404);

        $this->authorize('review', $application);

        $this->company = $company;
        $this->application = $application;
        $this->stage = $application->stage->value;
    }

    #[Computed]
    public function matchScore(): ?int
    {
        return app(MatchScoreCalculator::class)->calculate(
            $this->application->jobPosting,
            $this->application->candidateProfile->skills->pluck('id'),
        );
    }

    /**
     * Which of the posting's skills this candidate has, and which they do
     * not. The percentage at the top of the page is a number with no
     * working shown -- "86%" does not say whether the missing 14% is the
     * one thing the role is actually about. These two lists are the
     * working, and they are what a reviewer reads before deciding whether
     * to trust the score at all.
     */
    #[Computed]
    public function wantedSkillIds()
    {
        return $this->application->jobPosting->skills->pluck('id');
    }

    #[Computed]
    public function missingSkills()
    {
        $theyHave = $this->application->candidateProfile->skills->pluck('id');

        return $this->application->jobPosting->skills
            ->reject(fn ($skill) => $theyHave->contains($skill->id))
            ->values();
    }

    #[Computed]
    public function notes()
    {
        return $this->application->notes()->with('author')->get();
    }

    #[Computed]
    public function timeline()
    {
        return $this->application->events()->with('changedBy')->get();
    }

    public function updateStage(ChangeApplicationStage $changeStage): void
    {
        $this->authorize('updateStage', $this->application);

        $changeStage($this->application, auth()->user(), ApplicationStage::from($this->stage));

        $this->application->refresh();
        unset($this->timeline);

        Flux::toast(variant: 'success', text: __('Stage updated.'));
    }

    public function addNote(): void
    {
        $this->authorize('create', [ApplicationNote::class, $this->application]);

        $this->validate(['newNote' => ['required', 'string', 'max:5000']]);

        $this->application->notes()->create([
            'author_id' => auth()->id(),
            'note' => $this->newNote,
        ]);

        $this->reset('newNote');
        unset($this->notes);
    }

    public function startEditing(int $noteId): void
    {
        $note = $this->application->notes()->findOrFail($noteId);

        $this->authorize('update', $note);

        $this->editingNoteId = $note->id;
        $this->editingNoteBody = $note->note;
    }

    public function saveNote(): void
    {
        $note = $this->application->notes()->findOrFail($this->editingNoteId);

        $this->authorize('update', $note);

        $this->validate(['editingNoteBody' => ['required', 'string', 'max:5000']]);

        $note->update(['note' => $this->editingNoteBody]);

        $this->reset('editingNoteId', 'editingNoteBody');
        unset($this->notes);
    }

    public function deleteNote(int $noteId): void
    {
        $note = $this->application->notes()->findOrFail($noteId);

        $this->authorize('delete', $note);

        $note->delete();

        unset($this->notes);
    }
}; ?>

<div class="mx-auto flex max-w-4xl flex-col gap-6">
    @php
        $candidate = $this->application->candidateProfile;
    @endphp

    <div>
        <x-breadcrumb :items="[
            ['label' => __('Job postings'), 'url' => route('employer.jobs.index', $this->company)],
            ['label' => $this->application->jobPosting->title, 'url' => route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $this->application->jobPosting])],
            ['label' => $candidate->user->name],
        ]" />

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-display">{{ $candidate->user->name }}</flux:heading>
                @if ($candidate->headline)
                    <flux:text class="mt-1">{{ $candidate->headline }}</flux:text>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-match-score :score="$this->matchScore" size="md" />
                <x-application-status :application="$this->application" />
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-end gap-4">
            <flux:select wire:model="stage" :label="__('Review stage')" class="w-56">
                @foreach (ApplicationStage::cases() as $stage)
                    <flux:select.option value="{{ $stage->value }}">{{ $stage->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button variant="primary" wire:click="updateStage" wire:loading.attr="disabled" wire:target="updateStage">
                <span wire:loading.remove wire:target="updateStage">{{ __('Update') }}</span>
                <span wire:loading wire:target="updateStage">{{ __('Updating...') }}</span>
            </flux:button>

            @if ($this->application->resumeDocument)
                <flux:button
                    variant="subtle"
                    icon="arrow-down-tray"
                    :href="route('employer.applications.resume', ['company' => $this->company, 'application' => $this->application])"
                >
                    {{ __('Download CV') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- The candidate's profile is read live rather than frozen: only the CV
         and the screening answers are a snapshot of the moment they applied.
         Someone who has since added a certificate should be judged with it. --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Candidate') }}</flux:heading>

        <div class="mt-4 flex flex-col gap-4 text-sm">
            @if ($candidate->bio)
                <p class="text-zinc-700 dark:text-zinc-300">{{ $candidate->bio }}</p>
            @endif

            @if ($candidate->skills->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Their skills') }}
                        @if ($this->wantedSkillIds->isNotEmpty())
                            <span class="font-normal">{{ __('(highlighted ones are what this posting asks for)') }}</span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($candidate->skills as $skill)
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-300' => $this->wantedSkillIds->contains($skill->id),
                                'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => ! $this->wantedSkillIds->contains($skill->id),
                            ])>
                                {{ $skill->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->missingSkills->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Asked for, not on their profile') }}
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->missingSkills as $skill)
                            <span @class([
                                'rounded-full border border-dashed px-2.5 py-1 text-xs font-medium',
                                'border-warning-300 text-warning-700 dark:border-warning-700 dark:text-warning-300' => $skill->pivot->importance === \App\Enums\SkillImportance::Required,
                                'border-zinc-300 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400' => $skill->pivot->importance !== \App\Enums\SkillImportance::Required,
                            ])>
                                {{ $skill->name }}
                                @if ($skill->pivot->importance === \App\Enums\SkillImportance::Required)
                                    <span class="font-normal">{{ __('(required)') }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($this->application->cover_letter)
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Cover letter') }}</flux:heading>
            <div class="prose-content mt-4">{!! $this->application->cover_letter !!}</div>
        </div>
    @endif

    @if ($this->application->screeningAnswers->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Screening answers') }}</flux:heading>

            <dl class="mt-4 flex flex-col gap-4 text-sm">
                @foreach ($this->application->screeningAnswers as $answer)
                    <div>
                        <dt class="font-medium text-zinc-900 dark:text-zinc-100">{{ $answer->screeningQuestion->question_text }}</dt>
                        <dd class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $answer->answer_text }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Internal notes') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Only your team sees these. The candidate never does.') }}</flux:text>

        <form wire:submit="addNote" class="mt-4 flex flex-col gap-3">
            <flux:textarea wire:model="newNote" rows="3" :label="__('Add a note')" :placeholder="__('What stood out, what to ask next...')" />
            <div class="flex justify-end">
                <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addNote">
                    <span wire:loading.remove wire:target="addNote">{{ __('Add note') }}</span>
                    <span wire:loading wire:target="addNote">{{ __('Adding...') }}</span>
                </flux:button>
            </div>
        </form>

        @if ($this->notes->isNotEmpty())
            <ul class="mt-6 flex flex-col gap-4">
                @foreach ($this->notes as $note)
                    <li wire:key="note-{{ $note->id }}" class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50">
                        @if ($editingNoteId === $note->id)
                            <form wire:submit="saveNote" class="flex flex-col gap-3">
                                <flux:textarea wire:model="editingNoteBody" rows="3" :aria-label="__('Edit note')" />
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" type="button" wire:click="$set('editingNoteId', null)">{{ __('Cancel') }}</flux:button>
                                    <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveNote">
                                        <span wire:loading.remove wire:target="saveNote">{{ __('Save') }}</span>
                                        <span wire:loading wire:target="saveNote">{{ __('Saving...') }}</span>
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $note->note }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-500">
                                <span>{{ $note->author?->name ?? __('A former team member') }} &middot; {{ $note->created_at->diffForHumans() }}</span>

                                @can('update', $note)
                                    <span class="flex gap-2">
                                        <button type="button" wire:click="startEditing({{ $note->id }})" class="hover:text-brand-700 dark:hover:text-brand-400">{{ __('Edit') }}</button>
                                        <button type="button" wire:click="deleteNote({{ $note->id }})" wire:confirm="{{ __('Delete this note?') }}" class="hover:text-red-600">{{ __('Delete') }}</button>
                                    </span>
                                @endcan
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('History') }}</flux:heading>

        @if ($this->timeline->isEmpty())
            <flux:text class="mt-2">{{ __('Nothing yet. Stage changes show up here, with who made them.') }}</flux:text>
        @endif

        <ul class="mt-4 flex flex-col gap-3 text-sm">
            @foreach ($this->timeline as $event)
                @php
                    $movedTo = $event->to_stage !== null
                        ? (\App\Enums\ApplicationStage::tryFrom($event->to_stage)?->label() ?? $event->to_stage)
                        : (\App\Enums\ApplicationOutcomeStatus::tryFrom((string) $event->to_outcome_status)?->label() ?? $event->to_outcome_status);
                @endphp
                <li class="flex justify-between gap-4">
                    <span class="text-zinc-700 dark:text-zinc-300">
                        {{ $event->changedBy?->name ?? __('The candidate') }}
                        {{ __('moved this to :stage', ['stage' => $movedTo]) }}
                    </span>
                    <span class="shrink-0 text-zinc-500 dark:text-zinc-500">{{ $event->created_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
