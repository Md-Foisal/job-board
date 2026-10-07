<?php

use App\Actions\ChangeApplicationOutcome;
use App\Actions\ChangeApplicationStage;
use App\Actions\UndoApplicationOutcome;
use App\Enums\ApplicationOutcomeStatus;
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

    /**
     * The candidate's history, read live like the rest of their profile and
     * in the order their own profile page shows it, newest first.
     */
    #[Computed]
    public function experience()
    {
        return $this->application->candidateProfile->experienceRecords()->orderByDesc('start_date')->get();
    }

    #[Computed]
    public function education()
    {
        return $this->application->candidateProfile->educationRecords()->orderByDesc('start_date')->get();
    }

    #[Computed]
    public function certifications()
    {
        return $this->application->candidateProfile->certifications()->newestFirst()->get();
    }

    #[Computed]
    public function projects()
    {
        return $this->application->candidateProfile->projects()->newestFirst()->get();
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

    public function decide(ChangeApplicationOutcome $changeOutcome, string $outcome): void
    {
        $this->authorize('decideOutcome', $this->application);

        $to = ApplicationOutcomeStatus::tryFrom($outcome);

        abort_unless(in_array($to, [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true), 422);

        $changeOutcome($this->application, auth()->user(), $to);

        $this->application->refresh();
        unset($this->timeline);

        Flux::toast(variant: 'success', text: $to === ApplicationOutcomeStatus::Hired
            ? __('Marked as hired. The candidate is told in :minutes minutes; you can undo it until then.', ['minutes' => Application::UNDO_MINUTES])
            : __('Application rejected. The candidate is told in :minutes minutes; you can undo it until then.', ['minutes' => Application::UNDO_MINUTES]));
    }

    public function undoDecision(UndoApplicationOutcome $undo): void
    {
        abort_unless(auth()->user()->canManage($this->company), 403);

        // A page left open past the window still shows the button; the
        // click then meets a decision that is already final.
        if ($undo($this->application, auth()->user()) === null) {
            $this->application->refresh();

            Flux::toast(variant: 'warning', text: __('Too late to undo: the candidate has been told.'));

            return;
        }

        $this->application->refresh();
        $this->stage = $this->application->stage->value;
        unset($this->timeline);

        Flux::toast(variant: 'success', text: __('Decision undone. The candidate will not be told, and the application is open again.'));
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

<x-page>
    @php
        $candidate = $this->application->candidateProfile;
    @endphp

    <x-page-header
        :title="$candidate->user->name"
        :description="$candidate->headline"
        :back="route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $this->application->jobPosting])"
        :back-label="$this->application->jobPosting->title"
    >
        <x-slot:media>
            <flux:avatar
                circle
                size="lg"
                :src="$candidate->user->avatar ? \Illuminate\Support\Facades\Storage::url($candidate->user->avatar) : null"
                :name="$candidate->user->name"
                :initials="$candidate->user->initials()"
            />
        </x-slot:media>

        <x-slot:status>
            <x-match-score :score="$this->matchScore" size="md" />
            <x-application-status :application="$this->application" />
        </x-slot:status>
    </x-page-header>

    <x-card>
        <div class="flex flex-wrap items-end gap-4">
            @can('updateStage', $this->application)
                <flux:select wire:model="stage" :label="__('Review stage')" class="w-56">
                    @foreach (ApplicationStage::cases() as $stage)
                        <flux:select.option value="{{ $stage->value }}">{{ $stage->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button variant="primary" wire:click="updateStage" wire:loading.attr="disabled" wire:target="updateStage">
                    <span wire:loading.remove wire:target="updateStage">{{ __('Update') }}</span>
                    <span wire:loading wire:target="updateStage">{{ __('Updating...') }}</span>
                </flux:button>
            @else
                <flux:text>
                    {{ __('This application is closed (:outcome). Its stage no longer changes.', ['outcome' => $this->application->outcome_status->label()]) }}
                </flux:text>
            @endcan

            @if ($candidate->user->anonymized_at)
                {{-- The snapshot yields to erasure (AnonymizeUser): the file
                     is gone, so there is nothing to offer a link to. --}}
                <flux:text size="sm">{{ __('CV removed at the candidate\'s request') }}</flux:text>
            @elseif ($this->application->resumeDocument)
                <flux:button
                    variant="subtle"
                    icon="arrow-down-tray"
                    :href="route('employer.applications.resume', ['company' => $this->company, 'application' => $this->application])"
                >
                    {{ __('Download CV') }}
                </flux:button>
            @endif
        </div>
    </x-card>

    @can('decideOutcome', $this->application)
        <x-card class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="lg">{{ __('Decision') }}</flux:heading>
                <flux:text class="mt-1">{{ __('The candidate is told by email either way, :minutes minutes after you decide. Until then you can undo it.', ['minutes' => Application::UNDO_MINUTES]) }}</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button
                    variant="danger"
                    wire:click="decide('{{ ApplicationOutcomeStatus::Rejected->value }}')"
                    wire:confirm="{{ __('Reject this application? The candidate is told in :minutes minutes, and you can undo it until then.', ['minutes' => Application::UNDO_MINUTES]) }}"
                    wire:loading.attr="disabled"
                    wire:target="decide"
                >
                    {{ __('Reject') }}
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="decide('{{ ApplicationOutcomeStatus::Hired->value }}')"
                    wire:confirm="{{ __('Mark this candidate as hired? They are told in :minutes minutes, and you can undo it until then.', ['minutes' => Application::UNDO_MINUTES]) }}"
                    wire:loading.attr="disabled"
                    wire:target="decide"
                >
                    {{ __('Mark as hired') }}
                </flux:button>
            </div>
        </x-card>
    @endcan

    @can('undoOutcome', $this->application)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-warning-300 bg-warning-50 p-6 dark:border-warning-700 dark:bg-warning-950">
            <div class="min-w-0">
                <flux:heading size="lg">{{ __('Marked as :outcome', ['outcome' => $this->application->outcome_status->label()]) }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('The candidate is told :when. Until then you can undo it.', ['when' => $this->application->decided_at->addMinutes(Application::UNDO_MINUTES)->diffForHumans()]) }}
                </flux:text>
            </div>

            <flux:button wire:click="undoDecision" wire:loading.attr="disabled" wire:target="undoDecision">
                {{ __('Undo') }}
            </flux:button>
        </div>
    @endcan

    {{-- The candidate's profile is read live rather than frozen: only the CV
         and the screening answers are a snapshot of the moment they applied.
         Someone who has since added a certificate should be judged with it. --}}
    <x-card>
        <flux:heading size="lg">{{ __('Candidate') }}</flux:heading>

        <div class="mt-4 flex flex-col gap-4 text-sm">
            @if ($candidate->bio)
                <p class="text-ink-soft">{{ $candidate->bio }}</p>
            @endif

            <x-candidate-links :profile="$candidate" />

            @if ($candidate->skills->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-ink-muted">
                        {{ __('Their skills') }}
                        @if ($this->wantedSkillIds->isNotEmpty())
                            <span class="font-normal">{{ __('(highlighted ones are what this posting asks for)') }}</span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($candidate->skills as $skill)
                            <x-profile.skill-chip :skill="$skill" :variant="$this->wantedSkillIds->contains($skill->id) ? 'matched' : 'fact'" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->missingSkills->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-ink-muted">
                        {{ __('Asked for, not on their profile') }}
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->missingSkills as $skill)
                            @php $required = $skill->pivot->importance === \App\Enums\SkillImportance::Required; @endphp
                            <x-chip variant="missing" :required="$required">
                                {{ $skill->name }}
                                @if ($required)
                                    <span class="font-normal">{{ __('(required)') }}</span>
                                @endif
                            </x-chip>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-card>

    <x-candidate-history :experience="$this->experience" :education="$this->education" :certifications="$this->certifications" :projects="$this->projects" />

    @if ($this->application->cover_letter)
        <x-card>
            <flux:heading size="lg">{{ __('Cover letter') }}</flux:heading>
            <div class="prose-content mt-4">{!! $this->application->cover_letter !!}</div>
        </x-card>
    @endif

    @if ($this->application->screeningAnswers->isNotEmpty())
        <x-card>
            <flux:heading size="lg">{{ __('Screening answers') }}</flux:heading>

            <dl class="mt-4 flex flex-col gap-4 text-sm">
                @foreach ($this->application->screeningAnswers as $answer)
                    <div>
                        <dt class="font-medium text-ink">{{ $answer->screeningQuestion->question_text }}</dt>
                        <dd class="mt-1 text-ink-soft">{{ $answer->answer_text }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    @endif

    <x-card>
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
                    <li wire:key="note-{{ $note->id }}" class="rounded-lg bg-surface p-4">
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
                            <p class="whitespace-pre-line text-sm text-ink-soft">{{ $note->note }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-ink-muted">
                                <span>{{ $note->author?->name ?? __('A former team member') }} &middot; {{ $note->created_at->diffForHumans() }}</span>

                                @can('update', $note)
                                    <span class="flex gap-2">
                                        <button type="button" wire:click="startEditing({{ $note->id }})" class="hover:text-sunset-small">{{ __('Edit') }}</button>
                                        <button type="button" wire:click="deleteNote({{ $note->id }})" wire:confirm="{{ __('Delete this note?') }}" class="hover:text-danger-700 dark:hover:text-danger-300">{{ __('Delete') }}</button>
                                    </span>
                                @endcan
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card>
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
                    <span class="text-ink-soft">
                        {{ $event->changedBy?->name ?? __('The candidate') }}
                        @if ($event->to_outcome_status === \App\Enums\ApplicationOutcomeStatus::Active->value)
                            {{ __('undid the :outcome decision', ['outcome' => strtolower(\App\Enums\ApplicationOutcomeStatus::tryFrom((string) $event->from_outcome_status)?->label() ?? '')]) }}
                        @else
                            {{ __('moved this to :stage', ['stage' => $movedTo]) }}
                        @endif
                    </span>
                    <span class="shrink-0 text-ink-muted">{{ $event->created_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
</x-page>
