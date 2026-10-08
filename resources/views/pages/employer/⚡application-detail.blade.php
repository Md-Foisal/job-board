<?php

use App\Actions\ChangeApplicationOutcome;
use App\Actions\ChangeApplicationStage;
use App\Actions\UndoApplicationOutcome;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Http\Controllers\DocumentPreviewController;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Services\JobApplicants;
use App\Services\MatchScoreCalculator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One applicant, laid out as applicant tracking systems lay it out
 * (Workable, Greenhouse): the person and the actions on top, the
 * candidate's own material in tabs -- Profile, CV, Application -- and
 * the team's side, notes and history, in a column beside it.
 *
 * The list it was opened from travels in the address (stage, sort), so
 * previous and next walk the same rows in the same order, and the back
 * link returns to that list as it was.
 */
new #[Layout('layouts::employer')] #[Title('Application')] class extends Component {
    public const TABS = ['profile', 'cv', 'application'];

    public Company $company;

    public Application $application;

    #[Url(as: 'tab', except: 'profile')]
    public string $tab = 'profile';

    #[Url(as: 'stage', except: 'all')]
    public string $listStage = 'all';

    #[Url(as: 'sort', except: 'match')]
    public string $listSort = 'match';

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

        // Hand-edited addresses fall back to the defaults rather than
        // failing on a value the list never offers.
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'profile';
        }

        if (! JobApplicants::isStage($this->listStage)) {
            $this->listStage = 'all';
        }

        if (! in_array($this->listSort, JobApplicants::SORTS, true)) {
            $this->listSort = 'match';
        }
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'profile';
        }
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
     * The role they hold now, for the line under the headline: the
     * latest experience entry with no end date. A headline is the
     * candidate's own pitch; this is the fact behind it.
     */
    #[Computed]
    public function currentRole()
    {
        return $this->application->candidateProfile->experienceRecords()
            ->whereNull('end_date')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{previous: ?Application, next: ?Application, position: int, total: int}
     */
    #[Computed]
    public function neighbours(): array
    {
        return app(JobApplicants::class)->around($this->application, $this->listStage, $this->listSort);
    }

    #[Computed]
    public function notes()
    {
        return $this->application->notes()->with('author')->get();
    }

    /**
     * Oldest first, as a story: it starts with the application itself,
     * which the page adds, and each later entry says who moved it.
     */
    #[Computed]
    public function timeline()
    {
        return $this->application->events()->with('changedBy')->get()
            ->sortBy(fn ($event) => [$event->created_at->getTimestamp(), $event->id])
            ->values();
    }

    /**
     * One click per stage, as Workable's "move to another stage": no
     * select and Update button, and no saving on change either, since
     * every move shows on the candidate's own timeline.
     */
    public function moveTo(ChangeApplicationStage $changeStage, string $stage): void
    {
        $this->authorize('updateStage', $this->application);

        $to = ApplicationStage::tryFrom($stage);

        abort_if($to === null, 422);

        $changeStage($this->application, auth()->user(), $to);

        $this->application->refresh();
        unset($this->timeline, $this->neighbours);

        Flux::toast(variant: 'success', text: __('Moved to :stage.', ['stage' => $to->label()]));
    }

    public function decide(ChangeApplicationOutcome $changeOutcome, string $outcome): void
    {
        $this->authorize('decideOutcome', $this->application);

        $to = ApplicationOutcomeStatus::tryFrom($outcome);

        abort_unless(in_array($to, [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true), 422);

        $changeOutcome($this->application, auth()->user(), $to);

        $this->application->refresh();
        unset($this->timeline, $this->neighbours);

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
        unset($this->timeline, $this->neighbours);

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
        $user = $candidate->user;
        $jobPosting = $this->application->jobPosting;
        $document = $this->application->resumeDocument;
        $around = $this->neighbours;
        $isOpen = $this->application->outcome_status === ApplicationOutcomeStatus::Active;

        // The list this page was opened from, kept in every link out of it.
        $list = array_filter([
            'stage' => $listStage !== 'all' ? $listStage : null,
            'sort' => $listSort !== 'match' ? $listSort : null,
        ]);
        $linkTo = fn (Application $application, array $with = []) => route('employer.applications.show', [
            'company' => $this->company,
            'application' => $application,
            ...$list,
            ...$with,
        ]);
        // Walking to the next applicant keeps the tab open, so going through
        // CVs one after another stays on the CV.
        $walk = $tab !== 'profile' ? ['tab' => $tab] : [];
        $listUrl = route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $jobPosting, ...$list]);
    @endphp

    <x-page-header
        :title="$user->name"
        :description="$candidate->headline"
        :back="$listUrl"
        :back-label="$jobPosting->title"
    >
        <x-slot:media>
            <flux:avatar
                circle
                size="lg"
                :src="$user->avatar ? \Illuminate\Support\Facades\Storage::url($user->avatar) : null"
                :name="$user->name"
                :initials="$user->initials()"
            />
        </x-slot:media>

        <x-slot:status>
            <x-match-score :score="$this->matchScore" size="md" />
            <x-application-status :application="$this->application" />
        </x-slot:status>

        @if ($this->currentRole || $candidate->portfolio_url || $candidate->github_url || $candidate->linkedin_url)
            <div class="mt-1 flex flex-col gap-2">
                @if ($this->currentRole)
                    <p class="flex items-center gap-1.5">
                        <flux:icon.briefcase variant="micro" class="size-4 shrink-0" aria-hidden="true" />
                        {{ __(':title at :company', ['title' => $this->currentRole->job_title, 'company' => $this->currentRole->company_name]) }}
                    </p>
                @endif

                <x-candidate-links :profile="$candidate" class="sm:flex-row sm:flex-wrap sm:gap-x-5" />
            </div>
        @endif

        <x-slot:actions>
            @if ($around['total'] > 1 && $around['position'] > 0)
                {{-- The arrow keys walk the list too, as in Workable -- unless
                     the keys are being typed into a field. --}}
                <div
                    class="flex items-center gap-1"
                    role="group"
                    aria-label="{{ __('Other applicants for this job') }}"
                    x-data
                    x-on:keydown.window="
                        if ($event.altKey || $event.ctrlKey || $event.metaKey || $event.shiftKey) return;
                        if ($event.target.closest('input, textarea, select, [contenteditable], [role=dialog], [role=menu]')) return;
                        const button = $event.key === 'ArrowLeft' ? $refs.previous : ($event.key === 'ArrowRight' ? $refs.next : null);
                        const to = button?.getAttribute('href');
                        if (to) { $event.preventDefault(); Livewire.navigate(to); }
                    "
                >
                    <flux:tooltip :content="__('Previous applicant (←)')">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="chevron-left"
                            :href="$around['previous'] ? $linkTo($around['previous'], $walk) : null"
                            x-ref="previous"
                            :disabled="! $around['previous']"
                            wire:navigate
                            :aria-label="__('Previous applicant')"
                        />
                    </flux:tooltip>

                    <span class="text-sm tabular-nums text-ink-muted">{{ __(':position of :total', ['position' => $around['position'], 'total' => $around['total']]) }}</span>

                    <flux:tooltip :content="__('Next applicant (→)')">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="chevron-right"
                            :href="$around['next'] ? $linkTo($around['next'], $walk) : null"
                            x-ref="next"
                            :disabled="! $around['next']"
                            wire:navigate
                            :aria-label="__('Next applicant')"
                        />
                    </flux:tooltip>
                </div>
            @endif

            @can('decideOutcome', $this->application)
                <flux:button
                    size="sm"
                    icon="x-mark"
                    wire:click="decide('{{ ApplicationOutcomeStatus::Rejected->value }}')"
                    wire:confirm="{{ __('Reject this application? The candidate is told in :minutes minutes, and you can undo it until then.', ['minutes' => Application::UNDO_MINUTES]) }}"
                    wire:loading.attr="disabled"
                    wire:target="decide"
                >
                    {{-- Icon only on a phone, so the toolbar keeps to one row. --}}
                    <span class="max-sm:sr-only">{{ __('Reject') }}</span>
                </flux:button>

                <flux:button
                    size="sm"
                    icon="check"
                    wire:click="decide('{{ ApplicationOutcomeStatus::Hired->value }}')"
                    wire:confirm="{{ __('Mark this candidate as hired? They are told in :minutes minutes, and you can undo it until then.', ['minutes' => Application::UNDO_MINUTES]) }}"
                    wire:loading.attr="disabled"
                    wire:target="decide"
                >
                    <span class="max-sm:sr-only">{{ __('Mark as hired') }}</span>
                </flux:button>
            @endcan

            @can('updateStage', $this->application)
                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" variant="primary" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="moveTo">
                        <span wire:loading.remove wire:target="moveTo">{{ __('Move to') }}</span>
                        <span wire:loading wire:target="moveTo">{{ __('Moving...') }}</span>
                    </flux:button>

                    <flux:menu>
                        @foreach (ApplicationStage::cases() as $stage)
                            @if ($stage === $this->application->stage)
                                <flux:menu.item disabled icon="check">{{ __(':stage (now)', ['stage' => $stage->label()]) }}</flux:menu.item>
                            @else
                                <flux:menu.item wire:click="moveTo('{{ $stage->value }}')">{{ $stage->label() }}</flux:menu.item>
                            @endif
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (! $isOpen && ! auth()->user()->can('undoOutcome', $this->application))
        <x-card subtle padding="sm">
            <p class="flex items-center gap-2 text-sm text-ink-soft">
                <flux:icon.lock-closed variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                {{ __('This application is closed (:outcome). Its stage no longer changes.', ['outcome' => $this->application->outcome_status->label()]) }}
            </p>
        </x-card>
    @endif

    @can('undoOutcome', $this->application)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-card border border-warning-300 bg-warning-50 p-5 dark:border-warning-700 dark:bg-warning-950">
            <div class="min-w-0">
                <flux:heading size="lg" level="2">{{ __('Marked as :outcome', ['outcome' => $this->application->outcome_status->label()]) }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('The candidate is told :when. Until then you can undo it.', ['when' => $this->application->decided_at->addMinutes(Application::UNDO_MINUTES)->diffForHumans()]) }}
                </flux:text>
            </div>

            <flux:button wire:click="undoDecision" wire:loading.attr="disabled" wire:target="undoDecision">
                {{ __('Undo') }}
            </flux:button>
        </div>
    @endcan

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <x-tab-nav :label="__('About this applicant')">
                {{-- Switched on the page rather than by loading it, so a note
                     half written in the column beside survives a look at
                     the CV. --}}
                <x-tab-nav.item :href="$linkTo($this->application)" :current="$tab === 'profile'" action="$set('tab', 'profile')">{{ __('Profile') }}</x-tab-nav.item>
                <x-tab-nav.item :href="$linkTo($this->application, ['tab' => 'cv'])" :current="$tab === 'cv'" action="$set('tab', 'cv')">{{ __('CV') }}</x-tab-nav.item>
                <x-tab-nav.item :href="$linkTo($this->application, ['tab' => 'application'])" :current="$tab === 'application'" action="$set('tab', 'application')">{{ __('Application') }}</x-tab-nav.item>
            </x-tab-nav>

            @if ($tab === 'profile')
                {{-- Read live rather than frozen: only the CV and the
                     screening answers are a snapshot of the moment they
                     applied. Someone who has since added a certificate
                     should be judged with it. --}}
                <x-candidate-profile
                    :profile="$candidate"
                    mode="viewer"
                    :intro="false"
                    :wanted-skill-ids="$this->wantedSkillIds"
                    :missing-skills="$this->missingSkills"
                />
            @elseif ($tab === 'cv')
                @if ($user->anonymized_at)
                    {{-- The snapshot yields to erasure (AnonymizeUser): the
                         file is gone, so there is nothing to show. --}}
                    <x-empty-state icon="document" :heading="__('CV removed at the candidate\'s request')">
                        {{ __('Their account and its files were erased. The rest of this application stays for your records.') }}
                    </x-empty-state>
                @elseif ($document)
                    @php
                        $downloadUrl = route('employer.applications.resume', ['company' => $this->company, 'application' => $this->application]);
                    @endphp

                    <x-card padding="none" class="overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                            <p class="flex min-w-0 items-center gap-2 text-sm font-medium text-ink">
                                <flux:icon.document-text variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                                <span class="truncate">{{ $document->original_filename }}</span>
                            </p>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="$downloadUrl">{{ __('Download') }}</flux:button>
                        </div>

                        @if (DocumentPreviewController::canPreview($document))
                            @php
                                $previewUrl = route('employer.applications.resume.preview', ['company' => $this->company, 'application' => $this->application]);
                            @endphp

                            @if (DocumentPreviewController::isImage($document))
                                <img src="{{ $previewUrl }}" alt="{{ __('CV of :name', ['name' => $user->name]) }}" class="mx-auto max-h-[80vh] w-auto bg-surface">
                            @else
                                <iframe src="{{ $previewUrl }}" title="{{ __('CV of :name', ['name' => $user->name]) }}" class="block h-[80vh] w-full bg-surface"></iframe>
                            @endif
                        @else
                            <div class="px-5 py-10">
                                <x-empty-state icon="document-arrow-down" :level="3" :heading="__('This file opens once downloaded')" :action-href="$downloadUrl" :action-label="__('Download CV')">
                                    {{ __('Word files cannot be shown in the browser.') }}
                                </x-empty-state>
                            </div>
                        @endif
                    </x-card>
                @endif
            @else
                <x-card>
                    <flux:heading size="lg" level="2">{{ __('Cover letter') }}</flux:heading>

                    @if ($this->application->cover_letter)
                        <div class="prose-content mt-4">{!! $this->application->cover_letter !!}</div>
                    @else
                        <p class="mt-2 text-sm text-ink-muted">{{ __('They applied without a cover letter.') }}</p>
                    @endif
                </x-card>

                @if ($this->application->screeningAnswers->isNotEmpty())
                    <x-card>
                        <flux:heading size="lg" level="2">{{ __('Screening answers') }}</flux:heading>

                        <dl class="mt-4 flex flex-col gap-4 text-sm">
                            @foreach ($this->application->screeningAnswers as $answer)
                                <div>
                                    <dt class="font-medium text-ink">{{ $answer->screeningQuestion->question_text }}</dt>
                                    <dd class="mt-1 whitespace-pre-line text-ink-soft">{{ $answer->answer_text }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>
                @endif
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-6" aria-label="{{ __('Your team') }}">
            <x-card>
                <div class="flex items-center gap-2">
                    <flux:heading size="lg" level="2">{{ __('Notes') }}</flux:heading>
                    <x-visibility-badge :tip="__('Only your team sees notes. The candidate never does.')" />
                </div>

                <form wire:submit="addNote" class="mt-4 flex flex-col gap-3">
                    <flux:textarea wire:model="newNote" rows="3" :aria-label="__('Add a note')" :placeholder="__('What stood out, what to ask next...')" />
                    <div class="flex justify-end">
                        <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addNote">
                            <span wire:loading.remove wire:target="addNote">{{ __('Add note') }}</span>
                            <span wire:loading wire:target="addNote">{{ __('Adding...') }}</span>
                        </flux:button>
                    </div>
                </form>

                @if ($this->notes->isNotEmpty())
                    <ul class="mt-5 flex flex-col gap-3">
                        @foreach ($this->notes as $note)
                            <li wire:key="note-{{ $note->id }}" class="rounded-control bg-surface p-3">
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
                <flux:heading size="lg" level="2" class="mb-5">{{ __('History') }}</flux:heading>

                {{-- Always at least one entry: the application itself, which
                     the candidate's own timeline starts with too. --}}
                <ol class="relative border-s border-line">
                    <x-timeline-item :label="__('Applied')" :at="$this->application->created_at" highlight />

                    @foreach ($this->timeline as $event)
                        @php
                            $by = $event->changedBy?->name ?? __('The candidate');
                            $label = match (true) {
                                $event->to_stage !== null => __(':name moved this to :stage', [
                                    'name' => $by,
                                    'stage' => ApplicationStage::tryFrom($event->to_stage)?->label() ?? $event->to_stage,
                                ]),
                                $event->to_outcome_status === ApplicationOutcomeStatus::Active->value => __(':name undid the :outcome decision', [
                                    'name' => $by,
                                    'outcome' => strtolower(ApplicationOutcomeStatus::tryFrom((string) $event->from_outcome_status)?->label() ?? ''),
                                ]),
                                $event->to_outcome_status === ApplicationOutcomeStatus::Withdrawn->value => __(':name withdrew the application', ['name' => $by]),
                                default => __(':name marked this :outcome', [
                                    'name' => $by,
                                    'outcome' => ApplicationOutcomeStatus::tryFrom((string) $event->to_outcome_status)?->label() ?? $event->to_outcome_status,
                                ]),
                            };
                        @endphp

                        <x-timeline-item :label="$label" :at="$event->created_at" />
                    @endforeach
                </ol>
            </x-card>
        </aside>
    </div>
</x-page>
