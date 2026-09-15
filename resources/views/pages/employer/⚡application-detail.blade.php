<?php

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Services\MatchScoreCalculator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::employer')] class extends Component {
    public Company $company;

    public Application $application;

    public string $stage = '';

    public string $newNote = '';

    public ?int $editingNoteId = null;

    public string $editingNoteBody = '';

    public function mount(Company $company, Application $application): void
    {
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
            ['label' => $this->application->jobPosting->title, 'url' => route('employer.jobs.applications', ['company' => $this->company, 'job_posting' => $this->application->jobPosting])],
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
                @if ($this->matchScore !== null)
                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-success-700 dark:bg-success-950 dark:text-success-300">
                        {{ $this->matchScore }}% {{ __('match') }}
                    </span>
                @endif
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

            <flux:button variant="primary" wire:click="updateStage">{{ __('Update') }}</flux:button>

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
                <div class="flex flex-wrap gap-2">
                    @foreach ($candidate->skills as $skill)
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $skill->name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($this->application->cover_letter)
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Cover letter') }}</flux:heading>
            <p class="mt-4 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $this->application->cover_letter }}</p>
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
                <flux:button size="sm" variant="primary" type="submit">{{ __('Add note') }}</flux:button>
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
                                    <flux:button size="sm" variant="primary" type="submit">{{ __('Save') }}</flux:button>
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
