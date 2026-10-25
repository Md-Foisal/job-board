<?php

use App\Actions\ApplyCvSuggestions;
use App\Actions\RenderCvPdf;
use App\Actions\StoreCandidateDocument;
use App\Ai\Agents\CvWriter;
use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\CvFont;
use App\Enums\CvSection;
use App\Enums\DocumentType;
use App\Exceptions\CvPdfUnavailable;
use App\Jobs\PolishCvWithAi;
use App\Models\CvVersion;
use App\Models\Document;
use App\Models\ExperienceRecord;
use App\Support\AiQuota;
use App\Support\CvChecks;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvDocx;
use App\Support\CvPlainText;
use App\Support\CvQr;
use App\Support\CvScore;
use App\Support\CvSuggestions;
use App\Support\CvTailoring;
use App\Support\CvVersions;
use App\Support\DocumentUploads;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\LaravelPdf\Enums\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app')] #[Title('CV builder')] class extends Component {
    /**
     * Building a PDF is the heaviest thing a candidate can ask the server
     * for, so saving and downloading share an hourly allowance.
     */
    public const BUILDS_PER_HOUR = 10;

    /**
     * The settings a click in the Design tab changes, each saved with the
     * CV as soon as it changes.
     */
    private const DESIGN_PROPERTIES = ['template', 'accent', 'font', 'size', 'spacing', 'margins', 'headings', 'skillLayout', 'photoShape', 'withPhoto', 'paper'];

    /**
     * The saved CV being worked on. Everything on the page is saved to it
     * as it changes, so leaving and coming back finds it as it was.
     */
    #[Locked]
    public int $versionId;

    public string $versionName = '';

    /**
     * A headline and summary for this CV only, when it is aimed at a job;
     * empty means the profile's own.
     */
    public string $cvHeadline = '';

    public string $cvSummary = '';

    public bool $withPhoto = false;

    public string $paper = 'a4';

    public string $template = 'classic';

    public string $accent = 'ink';

    /**
     * The colour picker's value, kept apart from the accent so a colour
     * too light to read can be refused without losing the one in use.
     */
    public string $customColour = '#1E3A8A';

    /** The picked typeface, or '' for the template's own. */
    public string $font = '';

    public string $size = 'medium';

    public string $spacing = 'normal';

    public string $margins = 'normal';

    public string $headings = 'en';

    public string $skillLayout = 'line';

    public string $photoShape = 'square';

    /** @var list<string> */
    public array $sectionOrder = [];

    /** @var list<string> */
    public array $hiddenSections = [];

    /**
     * The CV saved by the last Save, offered for download until the next
     * change to the options.
     */
    #[Locked]
    public ?int $savedDocumentId = null;

    #[Locked]
    public bool $buildFailed = false;

    /**
     * Where the AI suggestions are: null (not asked), running, done,
     * failed, or unavailable (the allowance ran out before it started).
     */
    #[Locked]
    public ?string $aiStatus = null;

    /**
     * The checked suggestions, fixed on the server so the browser can only
     * change which of them are ticked.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $suggestions = [];

    /**
     * When the suggestions leave the cache. Applying some keeps the rest
     * only until then, never for a fresh hour.
     */
    #[Locked]
    public ?int $suggestionsExpireAt = null;

    public bool $useHeadline = false;

    public bool $useSummary = false;

    /** @var array<int, int> */
    public array $useRoles = [];

    /**
     * Opens the CV asked for in the address (?version=), or the one worked
     * on last, or a first "My CV". Suggestions written within the last
     * hour are shown again rather than asked for, and paid for, twice.
     */
    public function mount(): void
    {
        $this->loadVersion(CvVersions::open(auth()->user()->candidateProfile, request()->query('version')));

        if (! AiQuota::enabled()) {
            return;
        }

        $profileId = auth()->user()->candidateProfile->id;
        $result = Cache::get(PolishCvWithAi::resultKey($profileId));

        if (($result['status'] ?? null) === 'done' && ($result['suggestions']['version'] ?? null) === CvWriter::VERSION) {
            [$this->suggestions, $this->suggestionsExpireAt, $this->aiStatus] = [$result['suggestions'], $result['expires_at'] ?? null, 'done'];
            $this->tickSuggestions();
        } elseif (Cache::has(PolishCvWithAi::runningKey($profileId))) {
            $this->aiStatus = 'running';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::DESIGN_PROPERTIES, true)) {
            $this->persistDesign();
        }

        if ($property === 'versionName') {
            $this->validate(['versionName' => ['required', 'string', 'max:80']], [], ['versionName' => __('name')]);
            $this->version->update(['name' => trim($this->versionName)]);
            unset($this->versions);
        }

        if (in_array($property, ['cvHeadline', 'cvSummary'], true)) {
            $this->validate([
                'cvHeadline' => ['string', 'max:255'],
                'cvSummary' => ['string', 'max:2000'],
            ], [], ['cvHeadline' => __('headline'), 'cvSummary' => __('summary')]);

            $this->version->update([
                'headline' => trim($this->cvHeadline) === '' ? null : trim($this->cvHeadline),
                'summary' => trim($this->cvSummary) === '' ? null : trim($this->cvSummary),
            ]);
            $this->contentChanged();
        }
    }

    /**
     * A colour from the picker: used when it is dark enough to read as
     * text on white paper, refused with the reason otherwise.
     */
    public function updatedCustomColour(): void
    {
        $this->resetErrorBag('customColour');

        if (preg_match('/^#[0-9a-f]{6}$/i', $this->customColour) !== 1) {
            $this->addError('customColour', __('Pick a colour.'));

            return;
        }

        $contrast = CvDesign::contrastWithWhite($this->customColour);

        if ($contrast < CvDesign::MIN_CONTRAST) {
            $this->addError('customColour', __('This colour is too light to read on white paper (:ratio to 1; it needs at least :min to 1). Try a darker shade.', [
                'ratio' => number_format($contrast, 1),
                'min' => number_format(CvDesign::MIN_CONTRAST, 1),
            ]));

            return;
        }

        $this->accent = strtoupper($this->customColour);
        $this->persistDesign();
    }

    /**
     * The profile was changed from one of the sections on this page: the
     * preview and the checks are drawn again from the profile as it is
     * now, and a CV saved before the change is no longer the current one.
     */
    #[On('profile-updated')]
    public function profileChanged(): void
    {
        auth()->user()->unsetRelation('candidateProfile');
        unset($this->roles);
        $this->contentChanged();
    }

    /**
     * Another part of the page (this CV's own sections, its job, its web
     * page) changed the saved CV.
     */
    #[On('cv-updated')]
    public function versionChanged(): void
    {
        unset($this->version);
        $this->contentChanged();
    }

    /**
     * Shows or hides one section on this CV. The profile keeps it either
     * way.
     */
    public function toggleSection(string $section): void
    {
        if (CvSection::tryFrom($section) === null) {
            return;
        }

        $this->hiddenSections = in_array($section, $this->hiddenSections, true)
            ? array_values(array_diff($this->hiddenSections, [$section]))
            : [...$this->hiddenSections, $section];

        $this->persistDesign();
    }

    /**
     * Moves a section one place up (-1) or down (1), for the keyboard and
     * for phones, where dragging is awkward.
     */
    public function moveSection(string $section, int $by): void
    {
        $order = $this->design->order;
        $from = array_search($section, $order, true);
        $to = $from === false ? false : $from + ($by < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($order)) {
            return;
        }

        [$order[$from], $order[$to]] = [$order[$to], $order[$from]];
        $this->sectionOrder = $order;

        $this->persistDesign();
    }

    /**
     * A section dragged to a new place in the list (wire:sort).
     */
    public function sortSection(string $section, int $position): void
    {
        $order = array_values(array_diff($this->design->order, [$section]));

        if (CvSection::tryFrom($section) === null) {
            return;
        }

        array_splice($order, max(0, min($position, count($order))), 0, [$section]);
        $this->sectionOrder = $order;

        $this->persistDesign();
    }

    /**
     * Leaves one entry of the profile off this CV, or puts it back. The
     * entry must be the candidate's own.
     */
    public function toggleEntry(string $kind, int $id): void
    {
        $entries = $this->entries[$kind] ?? null;

        if ($entries === null || ! collect($entries)->contains('id', $id)) {
            return;
        }

        $version = $this->version;
        $hidden = $version->hidden_entries ?? [];
        $ids = $version->hiddenIds($kind);
        $hidden[$kind] = in_array($id, $ids, true) ? array_values(array_diff($ids, [$id])) : [...$ids, $id];

        $version->update(['hidden_entries' => $hidden]);
        unset($this->version);
        $this->contentChanged();
    }

    /**
     * Sets the parts a CV is expected to have in one part of the world.
     * The candidate can change each of them afterwards.
     */
    public function applyPreset(string $preset): void
    {
        $regional = [CvSection::Personal->value, CvSection::References->value, CvSection::Declaration->value];
        $version = $this->version;
        $extras = $version->cvExtras()->toArray();

        switch ($preset) {
            case 'international':
                $this->withPhoto = false;
                $this->headings = 'en';
                $this->hiddenSections = array_values(array_unique([...$this->hiddenSections, ...$regional]));
                break;

            case 'europass':
                $this->withPhoto = $this->photo !== null;
                $this->hiddenSections = array_values(array_unique([
                    ...array_diff($this->hiddenSections, [CvSection::Languages->value, CvSection::Personal->value]),
                    CvSection::References->value, CvSection::Declaration->value,
                ]));
                break;

            case 'bangladesh':
                $this->withPhoto = $this->photo !== null;
                $this->hiddenSections = array_values(array_diff($this->hiddenSections, $regional));
                $extras['declaration'] = true;
                $extras['reference_mode'] = $extras['reference_mode'] === 'none' ? 'on_request' : $extras['reference_mode'];
                $version->update(['extras' => $extras]);
                $this->dispatch('cv-extras-changed');
                break;

            default:
                return;
        }

        $this->persistDesign();

        Flux::toast(variant: 'success', text: match ($preset) {
            'international' => __('Set for the US, UK and most international employers: no photo or personal details.'),
            'europass' => __('Set for Europe: photo and languages on. Add your nationality and date of birth below if you want them.'),
            'bangladesh' => __('Set for Bangladesh: photo, personal information, references and a signed declaration. Fill in the personal details in the Content tab.'),
        });
    }

    /**
     * One step tighter towards one page (less room, smaller margins,
     * smaller type), saved like any other change. False when there is no
     * tighter step left, so the page stops trying.
     */
    public function tighten(): bool
    {
        $tighter = $this->design->tighter();

        if ($tighter === null) {
            return false;
        }

        $this->applyDesign($tighter);
        $this->persistDesign();

        return true;
    }

    /**
     * Opens another of the candidate's CVs.
     */
    public function openVersion(int $id): void
    {
        $version = auth()->user()->candidateProfile->cvVersions()->find($id);

        if ($version !== null) {
            $this->redirectRoute('candidate.cv-builder', ['version' => $version->id], navigate: true);
        }
    }

    /**
     * A copy of this CV to change for another kind of job.
     */
    public function copyVersion(): void
    {
        $profile = auth()->user()->candidateProfile;

        if ($profile->cvVersions()->count() >= CvVersion::MAX_PER_PROFILE) {
            Flux::toast(variant: 'warning', text: __('You can keep :count CVs. Delete one you no longer use to make another.', ['count' => CvVersion::MAX_PER_PROFILE]));

            return;
        }

        $copy = CvVersions::copy($this->version);

        $this->redirectRoute('candidate.cv-builder', ['version' => $copy->id], navigate: true);
    }

    /**
     * Deletes this CV and opens another. PDFs already saved from it stay
     * in the library; its web page stops answering. The last CV cannot be
     * deleted, so there is always one to open.
     */
    public function deleteVersion(): void
    {
        $profile = auth()->user()->candidateProfile;

        if ($profile->cvVersions()->count() <= 1) {
            return;
        }

        $this->version->delete();

        $this->redirectRoute('candidate.cv-builder', navigate: true);
    }

    #[Computed]
    public function version(): CvVersion
    {
        return auth()->user()->candidateProfile->cvVersions()->with('jobPosting.company')->findOrFail($this->versionId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, CvVersion>
     */
    #[Computed]
    public function versions()
    {
        return auth()->user()->candidateProfile->cvVersions()->latest('updated_at')->latest('id')->get(['id', 'name', 'job_posting_id', 'published_at', 'public_expires_at', 'public_slug']);
    }

    #[Computed]
    public function design(): CvDesign
    {
        return CvDesign::from($this->template, $this->accent, $this->sectionOrder, $this->hiddenSections, [
            'font' => $this->font,
            'size' => $this->size,
            'spacing' => $this->spacing,
            'margins' => $this->margins,
            'headings' => $this->headings,
            'qr' => (bool) ($this->version->design['qr'] ?? false),
            'skills' => $this->skillLayout,
            'photo_shape' => $this->photoShape,
        ]);
    }

    #[Computed]
    public function tailoring(): CvTailoring
    {
        return CvTailoring::for($this->version);
    }

    #[Computed]
    public function cv(): CvData
    {
        $version = $this->version;
        $cv = CvData::fromProfile(auth()->user()->candidateProfile, $version, $this->tailoring->keywords);

        return $this->design->qr && $version->isPublic() ? $cv->withQr(CvQr::for($version->publicUrl()), $version->publicUrl()) : $cv;
    }

    /**
     * @return array<int, array{gap: \App\Enums\CvGap, subject: ?string}>
     */
    #[Computed]
    public function checks(): array
    {
        return CvChecks::for($this->cv);
    }

    #[Computed]
    public function score(): CvScore
    {
        $roles = auth()->user()->candidateProfile->experienceRecords()
            ->whereNotIn('id', $this->version->hiddenIds('experience'))
            ->get(['start_date', 'end_date'])
            ->map(fn (ExperienceRecord $role) => ['start' => $role->start_date?->format('Y-m-d'), 'end' => $role->end_date?->format('Y-m-d')])
            ->all();

        $coverage = $this->tailoring->hasTarget() ? $this->tailoring->coverage($this->cv) : null;

        return CvScore::for($this->cv, $this->design, $coverage, $roles);
    }

    #[Computed]
    public function photo(): ?string
    {
        return $this->cv->photo();
    }

    #[Computed]
    public function preview(): string
    {
        return view($this->design->template->view(), [
            'cv' => $this->cv,
            'photo' => $this->withPhoto ? $this->photo : null,
            'design' => $this->design,
            'preview' => true,
            'fonts' => 'url',
        ])->render();
    }

    /**
     * Every entry of the profile a CV can leave out, by kind, for the
     * Design tab's lists.
     *
     * @return array<string, list<array{id: int, label: string}>>
     */
    #[Computed]
    public function entries(): array
    {
        $profile = auth()->user()->candidateProfile;

        return [
            'experience' => $profile->experienceRecords()->orderByDesc('start_date')->get()->map(fn ($role) => ['id' => $role->id, 'label' => __(':title at :company', ['title' => $role->job_title, 'company' => $role->company_name])])->all(),
            'projects' => $profile->projects()->newestFirst()->get()->map(fn ($project) => ['id' => $project->id, 'label' => $project->name])->all(),
            'education' => $profile->educationRecords()->orderByDesc('start_date')->get()->map(fn ($course) => ['id' => $course->id, 'label' => collect([$course->degree, $course->institution_name])->filter()->implode(', ')])->all(),
            'certifications' => $profile->certifications()->newestFirst()->get()->map(fn ($certification) => ['id' => $certification->id, 'label' => $certification->name])->all(),
            'awards' => $profile->awards()->newestFirst()->get()->map(fn ($award) => ['id' => $award->id, 'label' => $award->title])->all(),
            'volunteering' => $profile->volunteerExperiences()->newestFirst()->get()->map(fn ($role) => ['id' => $role->id, 'label' => __(':title at :company', ['title' => $role->role, 'company' => $role->organization])])->all(),
            'publications' => $profile->publications()->newestFirst()->get()->map(fn ($publication) => ['id' => $publication->id, 'label' => $publication->title])->all(),
        ];
    }

    /**
     * The CV that leaves the library when one more is saved, if the
     * library is already full.
     */
    #[Computed]
    public function leavingCv(): ?Document
    {
        $cvs = auth()->user()->candidateProfile->documents()
            ->where('document_type', DocumentType::Cv)
            ->latest()
            ->latest('id')
            ->get();

        return $cvs->count() >= DocumentUploads::RECENT_CVS_KEPT ? $cvs->last() : null;
    }

    #[Computed]
    public function savedDocument(): ?Document
    {
        return $this->savedDocumentId === null
            ? null
            : auth()->user()->candidateProfile->documents()->find($this->savedDocumentId);
    }

    #[Computed]
    public function aiAvailability(): AiAvailability
    {
        return AiQuota::availability(AiFeature::CvBuilder, auth()->user());
    }

    #[Computed]
    public function shownSuggestions(): ?CvSuggestions
    {
        return $this->suggestions === [] ? null : CvSuggestions::fromArray($this->suggestions);
    }

    /**
     * The profile's roles as they are now, to tell which suggestions were
     * overtaken by the candidate's own edits.
     *
     * @return \Illuminate\Support\Collection<int, ExperienceRecord>
     */
    #[Computed]
    public function roles()
    {
        return auth()->user()->candidateProfile->experienceRecords()->get()->keyBy('id');
    }

    public function polish(): void
    {
        if ($this->aiStatus === 'running' || ! $this->cv->hasContent()) {
            return;
        }

        if ($this->aiAvailability === AiAvailability::LimitReached) {
            $this->aiStatus = 'unavailable';

            return;
        }

        if ($this->aiAvailability !== AiAvailability::Available) {
            return;
        }

        $attempts = PolishCvWithAi::attemptsKey(auth()->user());

        if (RateLimiter::tooManyAttempts($attempts, PolishCvWithAi::DAILY_ATTEMPTS)) {
            Flux::toast(variant: 'warning', text: __('You can ask the AI for CV suggestions :count times a day. Try again tomorrow.', [
                'count' => PolishCvWithAi::DAILY_ATTEMPTS,
            ]));

            return;
        }

        $profileId = auth()->user()->candidateProfile->id;
        $this->aiStatus = 'running';

        // A second click, or the page open in another tab, finds the mark
        // already set and waits for the run already under way.
        if (! Cache::add(PolishCvWithAi::runningKey($profileId), true, PolishCvWithAi::RUNNING_SECONDS)) {
            return;
        }

        RateLimiter::hit($attempts, 24 * 60 * 60);
        Cache::forget(PolishCvWithAi::resultKey($profileId));
        $this->suggestions = [];

        PolishCvWithAi::dispatch(auth()->id());

        $this->checkAi();
    }

    /**
     * Polled while the AI writes. When the running mark has expired
     * without a result, the job never finished, and the page says so
     * instead of waiting for ever.
     */
    public function checkAi(): void
    {
        if ($this->aiStatus !== 'running') {
            return;
        }

        $profileId = auth()->user()->candidateProfile->id;
        $result = Cache::get(PolishCvWithAi::resultKey($profileId));

        if ($result === null) {
            if (! Cache::has(PolishCvWithAi::runningKey($profileId))) {
                $this->aiStatus = 'failed';
            }

            return;
        }

        if ($result['status'] !== 'done') {
            $this->aiStatus = $result['status'] === 'unavailable' ? 'unavailable' : 'failed';

            return;
        }

        [$this->suggestions, $this->suggestionsExpireAt, $this->aiStatus] = [$result['suggestions'], $result['expires_at'] ?? null, 'done'];
        unset($this->shownSuggestions);
        $this->tickSuggestions();
    }

    public function applySuggestions(ApplyCvSuggestions $apply): void
    {
        $suggestions = $this->shownSuggestions;

        if ($this->aiStatus !== 'done' || $suggestions === null) {
            return;
        }

        $profile = auth()->user()->candidateProfile;
        $result = $apply($profile, $suggestions, [
            'headline' => $this->useHeadline,
            'summary' => $this->useSummary,
            'experience' => array_map('intval', $this->useRoles),
        ]);

        if ($result['applied'] === [] && $result['skipped'] === 0) {
            Flux::toast(variant: 'warning', text: __('Tick at least one suggestion to apply.'));

            return;
        }

        $this->suggestions = $suggestions->without($result['applied'])->toArray();
        $expiresAt = $this->suggestionsExpireAt ?? now()->addSeconds(PolishCvWithAi::RESULT_SECONDS)->getTimestamp();

        if ($expiresAt > now()->getTimestamp()) {
            Cache::put(
                PolishCvWithAi::resultKey($profile->id),
                ['status' => 'done', 'suggestions' => $this->suggestions, 'expires_at' => $expiresAt],
                now()->setTimestamp($expiresAt),
            );
        }

        auth()->user()->unsetRelation('candidateProfile');
        unset($this->shownSuggestions, $this->roles);
        $this->contentChanged();
        $this->tickSuggestions();

        Flux::toast(variant: $result['skipped'] > 0 ? 'warning' : 'success', text: trim(
            trans_choice('{0} Nothing was applied.|{1} Applied 1 suggestion to your profile.|[2,*] Applied :count suggestions to your profile.', count($result['applied']))
            .' '.trans_choice('{0}|{1} 1 was left out because you changed that part since.|[2,*] :count were left out because you changed those parts since.', $result['skipped']),
        ));
    }

    public function discardSuggestions(): void
    {
        Cache::forget(PolishCvWithAi::resultKey(auth()->user()->candidateProfile->id));
        [$this->suggestions, $this->suggestionsExpireAt, $this->aiStatus] = [[], null, null];
        [$this->useHeadline, $this->useSummary, $this->useRoles] = [false, false, []];
        unset($this->shownSuggestions);
    }

    /**
     * A suggestion starts ticked only when it still fits the profile as it
     * is, can be applied, and brings no number the candidate did not write.
     */
    private function tickSuggestions(): void
    {
        $suggestions = $this->shownSuggestions;
        $profile = auth()->user()->candidateProfile;

        $this->useHeadline = $suggestions?->headline !== null && $suggestions->headline['new_numbers'] === [] && $suggestions->headlineIsCurrent($profile);
        $this->useSummary = $suggestions?->summary !== null && $suggestions->summary['new_numbers'] === [] && $suggestions->summaryIsCurrent($profile);
        $this->useRoles = collect($suggestions?->experience ?? [])
            ->filter(fn (array $role) => ! $role['too_long'] && $role['new_numbers'] === [] && CvSuggestions::roleIsCurrent($role, $this->roles->get($role['id'])))
            ->pluck('id')
            ->all();
    }

    public function save(StoreCandidateDocument $store): void
    {
        $pdf = $this->build();

        if ($pdf === null) {
            return;
        }

        // Built first, stored second: a failed build never leaves a
        // document behind, and the rotation only happens for a real file.
        $document = $store->generated(auth()->user()->candidateProfile, DocumentType::Cv, $pdf, $this->cv->fileName());

        $this->savedDocumentId = $document->id;
        unset($this->leavingCv, $this->savedDocument);
    }

    public function download(): ?StreamedResponse
    {
        $pdf = $this->build();

        if ($pdf === null) {
            return null;
        }

        return response()->streamDownload(fn () => print ($pdf), $this->cv->fileName(), ['Content-Type' => 'application/pdf']);
    }

    /**
     * The CV as a Word file: one column, the CV's sections and order, for
     * employers and sites that ask for Word.
     */
    public function downloadWord(): ?StreamedResponse
    {
        if (! $this->cv->hasContent()) {
            return null;
        }

        $docx = CvDocx::for($this->cv, $this->design, $this->paper === 'letter' ? 'letter' : 'a4');

        return response()->streamDownload(fn () => print ($docx), preg_replace('/\.pdf$/', '.docx', $this->cv->fileName()), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * The CV as plain text, for application forms that ask to paste one.
     */
    public function downloadText(): ?StreamedResponse
    {
        if (! $this->cv->hasContent()) {
            return null;
        }

        $text = CvPlainText::for($this->cv, $this->design);

        return response()->streamDownload(fn () => print ($text), preg_replace('/\.pdf$/', '.txt', $this->cv->fileName()), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function build(): ?string
    {
        $this->validate([
            'withPhoto' => ['boolean'],
            'paper' => ['required', 'in:'.collect(RenderCvPdf::PAPERS)->map(fn (Format $format) => $format->value)->implode(',')],
        ]);

        $this->buildFailed = false;

        if (! $this->cv->hasContent()) {
            return null;
        }

        $key = 'cv-build:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::BUILDS_PER_HOUR)) {
            $this->addError('build', trans_choice("{1} You've built :limit CVs in the last hour. Try again in 1 minute.|[2,*] You've built :limit CVs in the last hour. Try again in :count minutes.", SubmissionLimits::minutesUntilAvailable($key), [
                'limit' => self::BUILDS_PER_HOUR,
            ]));

            return null;
        }

        RateLimiter::hit($key, 60 * 60);

        try {
            return app(RenderCvPdf::class)($this->cv, $this->withPhoto && $this->photo !== null, Format::from($this->paper), $this->design);
        } catch (CvPdfUnavailable $exception) {
            $this->addError('build', __('Your CV has text that only our PDF service can draw, and it could not be reached just now. Try again in a few minutes.'));

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->buildFailed = true;

            return null;
        }
    }

    private function loadVersion(CvVersion $version): void
    {
        $saved = $version->design ?? [];

        $this->versionId = $version->id;
        $this->versionName = $version->name;
        $this->cvHeadline = (string) $version->headline;
        $this->cvSummary = (string) $version->summary;
        $this->withPhoto = (bool) ($saved['photo'] ?? false);
        $this->paper = in_array($saved['paper'] ?? null, ['a4', 'letter'], true) ? $saved['paper'] : 'a4';
        $this->applyDesign($version->cvDesign());
    }

    /**
     * The design as it is now, cleaned and saved with the CV. Whatever
     * the browser sent that was not known is gone from the page too.
     */
    private function persistDesign(): void
    {
        unset($this->design);
        $design = $this->design;
        $this->applyDesign($design);

        $this->version->update(['design' => [
            ...$design->toArray(),
            'photo' => $this->withPhoto,
            'paper' => in_array($this->paper, ['a4', 'letter'], true) ? $this->paper : 'a4',
        ]]);

        $this->contentChanged();
    }

    /**
     * Brings the public properties in line with a cleaned design.
     */
    private function applyDesign(CvDesign $design): void
    {
        $this->template = $design->template->value;
        $this->accent = $design->accent;
        $this->font = $design->font?->value ?? '';
        $this->size = $design->size;
        $this->spacing = $design->spacing;
        $this->margins = $design->margins;
        $this->headings = $design->headings;
        $this->skillLayout = $design->skillLayout;
        $this->photoShape = $design->photoShape;
        $this->sectionOrder = $design->order;
        $this->hiddenSections = $design->hidden;

        if (str_starts_with($design->accent, '#')) {
            $this->customColour = $design->accent;
        }

        unset($this->design);
    }

    /**
     * What the preview, the checks and the score are drawn from changed,
     * so they are drawn again, and a CV saved before the change is no
     * longer the current one.
     */
    private function contentChanged(): void
    {
        unset($this->version, $this->cv, $this->checks, $this->score, $this->preview, $this->tailoring, $this->entries, $this->photo);
        $this->savedDocumentId = null;
        $this->buildFailed = false;
    }
}; ?>

@php
    $cv = $this->cv;
    $checks = $this->checks;
    $score = $this->score;
    $version = $this->version;
    $unsupported = $cv->unsupportedScriptParts(\App\Actions\RenderCvPdf::usesBrowser());
    $paperSize = $paper === 'letter' ? ['w' => 215.9, 'h' => 279.4] : ['w' => 210, 'h' => 297];
@endphp

<x-page>
    {{-- Editor and preview side by side, as in the resume builders people
         know (Reactive Resume and the like): writing on the left changes
         the page on the right. The preview can be put away to give the
         editor the width, and stays put away on the next visit. On a
         phone it starts put away. --}}
    <div
        x-data="{
            preview: (() => {
                try {
                    const saved = localStorage.getItem('cv-builder.preview');
                    if (saved !== null) return saved === 'shown';
                } catch (e) {}
                return window.matchMedia('(min-width: 1024px)').matches;
            })(),
            tab: 'content',
            togglePreview() {
                this.preview = ! this.preview;
                try { localStorage.setItem('cv-builder.preview', this.preview ? 'shown' : 'hidden'); } catch (e) {}
            },
        }"
        class="flex flex-col gap-6"
    >
        <x-page-header :title="__('CV builder')">
            <x-slot:actions>
                <flux:button x-on:click="togglePreview()" icon="eye" x-bind:aria-pressed="preview ? 'true' : 'false'">
                    <span x-show="! preview">{{ __('Show preview') }}</span>
                    <span x-show="preview" x-cloak>{{ __('Hide preview') }}</span>
                </flux:button>
                @if ($cv->hasContent())
                    {{-- Flux shows a spinner and ignores clicks while each runs. --}}
                    <flux:button.group>
                        <flux:button wire:click="download" icon="arrow-down-tray">{{ __('Download PDF') }}</flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button icon="chevron-down" :aria-label="__('Other formats')" />
                            <flux:menu>
                                <flux:menu.item wire:click="downloadWord" icon="document-text">{{ __('Word (.docx)') }}</flux:menu.item>
                                <flux:menu.item wire:click="downloadText" icon="bars-3-bottom-left">{{ __('Plain text (.txt)') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:button.group>
                    <flux:button wire:click="save" variant="primary" class="btn-sunset" icon="document-plus">{{ __('Save to my CVs') }}</flux:button>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>{{ session('status') }}</flux:callout.text>
            </flux:callout>
        @endif

        @if ($version->jobPosting)
            <flux:callout icon="briefcase" class="!py-3">
                <flux:callout.text>
                    {{ __('This CV is aimed at :title at :company.', ['title' => $version->jobPosting->title, 'company' => $version->jobPosting->company->name]) }}
                    <button type="button" x-on:click="tab = 'share'" class="font-medium underline">{{ __('See how it covers the job') }}</button>
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Which of the candidate's saved CVs this is. --}}
        <x-card as="section" padding="sm" aria-label="{{ __('Your CVs') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <flux:select :label="__('CV')" x-on:change="$wire.openVersion(Number($event.target.value))">
                    @foreach ($this->versions as $option)
                        <flux:select.option :value="$option->id" :selected="$option->id === $versionId">
                            {{ $option->name }}@if ($option->isPublic()) · {{ __('shared') }}@endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:modal.trigger name="rename-cv">
                <flux:button icon="pencil" size="sm">{{ __('Rename') }}</flux:button>
            </flux:modal.trigger>
            <flux:button wire:click="copyVersion" icon="document-duplicate" size="sm">{{ __('Make a copy') }}</flux:button>
            @if ($this->versions->count() > 1)
                <flux:button wire:click="deleteVersion" wire:confirm="{{ __('Delete this CV? PDFs you saved from it stay in your documents.') }}" variant="ghost" icon="trash" size="sm">{{ __('Delete') }}</flux:button>
            @endif
            <a href="{{ route('candidate.cover-letters') }}" wire:navigate class="ms-auto self-center text-sm font-medium text-sunset-small hover:underline">{{ __('Write a cover letter') }}</a>
        </x-card>

        <flux:modal name="rename-cv" class="w-full max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Rename this CV') }}</flux:heading>
                <flux:input wire:model.blur.live="versionName" :label="__('Name')" :description="__('Only you see it, to tell your CVs apart: “Backend roles”, “For Acme”.')" maxlength="80" />
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        {{-- What came of the last save or download. --}}
        @if ($this->savedDocument)
            <div role="status" class="flex flex-wrap items-center gap-2 rounded-control bg-success-50 p-3 text-sm text-success-700 dark:bg-success-950 dark:text-success-300">
                <flux:icon name="check-circle" variant="mini" />
                <span>{{ __('Saved to your CVs as :name.', ['name' => $this->savedDocument->original_filename]) }}</span>
                <a href="{{ route('candidate.documents.download', $this->savedDocument) }}" class="font-medium underline">{{ __('Download') }}</a>
                <a href="{{ route('candidate.documents.index') }}" wire:navigate class="font-medium underline">{{ __('View documents') }}</a>
            </div>
        @elseif ($this->leavingCv && $cv->hasContent())
            <flux:text size="sm" class="text-warning-700 dark:text-warning-300">
                {{ __('You keep your :count most recent CVs, so saving moves your oldest, :name, out of your library. Applications already sent with it keep it.', ['count' => \App\Support\DocumentUploads::RECENT_CVS_KEPT, 'name' => $this->leavingCv->original_filename]) }}
            </flux:text>
        @endif

        @if ($buildFailed)
            <p role="alert" class="text-sm text-danger-700 dark:text-danger-300">{{ __("We couldn't build the PDF. Nothing was saved; please try again.") }}</p>
        @endif

        @error('build')
            <p role="alert" class="text-sm text-danger-700 dark:text-danger-300">{{ $message }}</p>
        @enderror

        <div class="grid items-start gap-6" x-bind:class="preview ? 'lg:grid-cols-2' : ''">
            {{-- The editor --}}
            <div class="flex min-w-0 flex-col gap-6">
                <div role="tablist" aria-label="{{ __('CV builder') }}" class="flex gap-1 rounded-control bg-surface p-1 ring-1 ring-line">
                    @foreach (['content' => __('Content'), 'design' => __('Design'), 'share' => __('Job & sharing')] as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            id="cv-tab-{{ $key }}"
                            aria-controls="cv-panel-{{ $key }}"
                            x-bind:aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                            x-on:click="tab = '{{ $key }}'"
                            x-bind:class="tab === '{{ $key }}' ? 'bg-canvas text-ink shadow-xs ring-1 ring-line' : 'text-ink-muted hover:text-ink'"
                            class="flex-1 rounded-control px-3 py-2 text-sm font-medium transition"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Content: the profile itself, edited here. Every change is
                     a change to the profile, so the CV and what companies
                     see stay one and the same. Below it, the parts kept with
                     this CV only. --}}
                <div id="cv-panel-content" role="tabpanel" aria-labelledby="cv-tab-content" x-show="tab === 'content'" class="flex flex-col gap-6">
                    @if ($cv->hasContent())
                        {{-- The score: our own checklist of what recruiters and
                             resume checkers look for. --}}
                        <x-card as="section" aria-labelledby="cv-score-heading">
                            <div class="flex items-center gap-4">
                                <div @class([
                                    'flex size-14 shrink-0 items-center justify-center rounded-full text-lg font-semibold ring-4',
                                    'text-success-700 ring-success-200 dark:text-success-300 dark:ring-success-800' => $score->score >= 80,
                                    'text-warning-700 ring-warning-200 dark:text-warning-300 dark:ring-warning-800' => $score->score >= 50 && $score->score < 80,
                                    'text-danger-700 ring-danger-200 dark:text-danger-300 dark:ring-danger-800' => $score->score < 50,
                                ])>
                                    {{ $score->score }}
                                </div>
                                <div>
                                    <flux:heading id="cv-score-heading">{{ __('CV score') }}</flux:heading>
                                    <flux:text size="sm">{{ __(':passed of :total checks pass. Our own reading of what recruiters and application systems look for.', ['passed' => count($score->checks) - count($score->failing()), 'total' => count($score->checks)]) }}</flux:text>
                                </div>
                            </div>
                            @if ($score->failing() !== [])
                                <ul class="mt-4 space-y-3">
                                    @foreach ($score->failing() as $check)
                                        <li wire:key="score-{{ $check['key'] }}" class="flex gap-3 text-sm">
                                            <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0 text-warning-600 dark:text-warning-400" aria-hidden="true" />
                                            <div>
                                                <p class="font-medium text-ink">{{ $check['label'] }}</p>
                                                <p class="text-ink-soft">{{ $check['advice'] }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <details class="mt-4 text-sm">
                                <summary class="cursor-pointer text-ink-muted">{{ __('What passes') }}</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($score->checks as $check)
                                        @if ($check['passed'])
                                            <li class="flex items-center gap-2 text-ink-soft">
                                                <flux:icon.check-circle variant="micro" class="text-success-700 dark:text-success-300" aria-hidden="true" />{{ $check['label'] }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </details>
                        </x-card>
                    @endif

                    @if ($checks !== [])
                        <x-card as="section">
                            <flux:heading>{{ __('Worth adding') }}</flux:heading>
                            <ul class="mt-3 space-y-2">
                                @foreach ($checks as $check)
                                    <li wire:key="check-{{ $loop->index }}" class="flex items-start justify-between gap-3 text-sm">
                                        <span class="text-ink-soft">{{ $check['gap']->message($check['subject']) }}</span>
                                        <a href="{{ route($check['gap']->route()) }}" wire:navigate class="shrink-0 font-medium text-sunset-small hover:underline">{{ __('Add') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                    @endif

                    @if ($unsupported !== [])
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.heading>{{ __("Some text may not show correctly in the PDF") }}</flux:callout.heading>
                            <flux:callout.text>
                                @if (\App\Actions\RenderCvPdf::usesBrowser())
                                    {{ __('Text in your :parts is in a script whose letters we do not ship, so the PDF may draw it with a different font or as empty boxes. Bengali and Latin letters are fine.', ['parts' => collect($unsupported)->map(fn ($part) => strtolower(__($part)))->join(', ', ' '.__('and').' ')]) }}
                                @else
                                    {{ __('Text in your :parts is in a script our PDF maker cannot draw properly yet, such as Bengali or Arabic. The preview here is right, but in the PDF it may show as empty boxes or with letters out of order. Writing those parts in English avoids it.', ['parts' => collect($unsupported)->map(fn ($part) => strtolower(__($part)))->join(', ', ' '.__('and').' ')]) }}
                                @endif
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    {{-- The top of the CV: contact lines and summary, edited in
                         the same dialogs as on the profile page. --}}
                    @php
                        $profile = auth()->user()->candidateProfile;
                    @endphp
                    <x-profile.section :heading="__('Contact and summary')">
                        <dl class="divide-y divide-line border-t border-line text-sm">
                            @foreach ([
                                ['label' => __('Headline'), 'value' => $profile->headline, 'dialog' => 'edit-intro'],
                                ['label' => __('Links'), 'value' => collect($cv->links)->pluck('text')->implode(' · '), 'dialog' => 'edit-intro'],
                                ['label' => __('Phone and location'), 'value' => collect([$profile->phone, $profile->location])->filter()->implode(' · '), 'dialog' => 'edit-contact'],
                                ['label' => __('Summary'), 'value' => $profile->bio, 'dialog' => 'edit-about'],
                            ] as $row)
                                <div class="flex items-start gap-4 px-5 py-3 sm:px-6">
                                    <dt class="w-36 shrink-0 font-medium text-ink">{{ $row['label'] }}</dt>
                                    <dd class="min-w-0 flex-1 {{ filled($row['value']) ? 'text-ink-soft' : 'text-ink-muted' }}">
                                        <span class="line-clamp-2">{{ filled($row['value']) ? $row['value'] : __('Not added') }}</span>
                                    </dd>
                                    <flux:modal.trigger :name="$row['dialog']">
                                        <flux:button variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :part', ['part' => strtolower($row['label'])])" />
                                    </flux:modal.trigger>
                                </div>
                            @endforeach
                        </dl>
                        {{-- A headline and summary for this CV alone, for a CV
                             aimed at one kind of job. --}}
                        <details class="border-t border-line px-5 py-4 sm:px-6" @if (filled($cvHeadline) || filled($cvSummary)) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-ink">{{ __('A different headline and summary on this CV only') }}</summary>
                            <div class="mt-4 space-y-4">
                                <flux:input wire:model.blur.live="cvHeadline" :label="__('Headline on this CV')" :placeholder="$profile->headline ?: __('Same as your profile')" maxlength="255" />
                                <flux:textarea wire:model.blur.live="cvSummary" :label="__('Summary on this CV')" :placeholder="$profile->bio ? \Illuminate\Support\Str::limit($profile->bio, 160) : __('Same as your profile')" rows="4" maxlength="2000" :description="__('Leave empty to use your profile\'s. Your profile, which companies read, does not change.')" />
                            </div>
                        </details>
                    </x-profile.section>

                    <livewire:profile.experience-section />
                    <livewire:profile.projects-section />
                    <livewire:profile.education-section />
                    <livewire:profile.skills-section />
                    <livewire:profile.certifications-section />
                    <livewire:profile.languages-section />
                    <livewire:profile.awards-section />
                    <livewire:profile.volunteering-section />
                    <livewire:profile.publications-section />

                    <livewire:cv.extras :version-id="$versionId" :key="'cv-extras-'.$versionId" />

                    {{-- AI suggestions: offered, running, failed, or out of
                         allowance. With AI off, or a plan without it, nothing
                         shows here. --}}
                    @php
                        $availability = $this->aiAvailability;
                    @endphp

                    @if ($aiStatus === 'running')
                        <x-ai-working wire:poll.2s="checkAi" :heading="__('Writing suggestions…')">{{ __('This usually takes under a minute. You can stay on this page.') }}</x-ai-working>
                    @elseif ($aiStatus === 'unavailable' || ($aiStatus === null && $availability === \App\Enums\AiAvailability::LimitReached))
                        <flux:text size="sm">
                            {{ __("You've used this month's AI suggestions. They reset on :date.", ['date' => \App\Support\LocalTime::of(now()->startOfMonth()->addMonth())->format(\App\Support\DateFormat::MOMENT)]) }}
                        </flux:text>
                    @elseif ($cv->hasContent() && $availability === \App\Enums\AiAvailability::Available && in_array($aiStatus, [null, 'failed'], true))
                        <x-card as="section">
                            @if ($aiStatus === 'failed')
                                <flux:heading>{{ __("The AI couldn't write suggestions this time") }}</flux:heading>
                                <flux:text size="sm" class="mt-1">{{ __('You can try again. Your CV does not depend on it.') }}</flux:text>
                            @else
                                <flux:heading>{{ __('Improve the wording with AI') }}</flux:heading>
                                <flux:text size="sm" class="mt-1">{{ __('Suggests a headline, a summary and bullet points for your roles, from what your profile already says. You choose what goes into your profile.') }}</flux:text>
                            @endif
                            <flux:button wire:click="polish" icon="sparkles" size="sm" class="mt-3">
                                {{ $aiStatus === 'failed' ? __('Try again') : __('Improve with AI') }}
                            </flux:button>
                            <flux:text size="sm" class="mt-2 text-ink-muted">
                                {{ __('Your headline, summary, roles, education and skills (not your name, contact details, links or photo) are sent to :provider.', ['provider' => \App\Support\AiProvider::name()]) }} {{ \App\Support\AiProvider::handling('them') }}
                            </flux:text>
                        </x-card>
                    @endif
                </div>

                {{-- Design: how this CV looks. Nothing here changes the
                     profile. --}}
                <div id="cv-panel-design" role="tabpanel" aria-labelledby="cv-tab-design" x-show="tab === 'design'" x-cloak class="flex flex-col gap-6">
                    {{-- One click for what a CV is expected to carry where it
                         will be read. --}}
                    <x-card as="section">
                        <flux:heading>{{ __('Where will it be read?') }}</flux:heading>
                        <flux:text size="sm" class="mt-1">{{ __('Sets the photo and the sections employers there expect. You can change each part afterwards.') }}</flux:text>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            @foreach ([
                                'international' => [__('International'), __('US, UK, most online applications: no photo or personal details.')],
                                'europass' => [__('Europe'), __('Photo and languages, the Europass way.')],
                                'bangladesh' => [__('Bangladesh'), __('Photo, personal information, references and a declaration.')],
                            ] as $key => [$label, $hint])
                                <button type="button" wire:click="applyPreset('{{ $key }}')" class="rounded-control p-3 text-start ring-1 ring-line transition hover:ring-line-strong focus-visible:ring-2 focus-visible:ring-ink">
                                    <span class="block text-sm font-medium text-ink">{{ $label }}</span>
                                    <span class="block text-xs text-ink-muted">{{ $hint }}</span>
                                </button>
                            @endforeach
                        </div>
                    </x-card>

                    <x-card as="section">
                        <flux:heading>{{ __('Template') }}</flux:heading>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('Template') }}">
                            @foreach (\App\Enums\CvTemplate::cases() as $option)
                                <label @class([
                                    'flex cursor-pointer flex-col gap-1 rounded-control p-3 ring-1 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ink',
                                    'bg-surface ring-2 ring-ink' => $template === $option->value,
                                    'ring-line hover:ring-line-strong' => $template !== $option->value,
                                ])>
                                    <input type="radio" wire:model.live="template" value="{{ $option->value }}" class="sr-only">
                                    <x-cv-template-sketch :template="$option" :accent="$option->usesAccent() ? \App\Support\CvDesign::from($option->value, $accent)->colour() : \App\Support\CvDesign::ACCENTS['ink']" class="mb-2" />
                                    <span class="font-medium text-ink">{{ __($option->label()) }}</span>
                                    <span class="text-xs text-ink-muted">{{ __($option->description()) }}</span>
                                    @if ($option->readsWellInTrackingSystems())
                                        <span class="mt-1 inline-flex w-fit items-center gap-1 text-xs font-medium text-success-700 dark:text-success-300">
                                            <flux:icon.check-circle variant="micro" aria-hidden="true" />
                                            {{ __('Reads well in application systems') }}
                                        </span>
                                    @else
                                        <span class="mt-1 inline-flex w-fit items-center gap-1 text-xs font-medium text-warning-700 dark:text-warning-300">
                                            <flux:icon.exclamation-triangle variant="micro" aria-hidden="true" />
                                            {{ __('Application systems may read it out of order') }}
                                        </span>
                                    @endif
                                </label>
                            @endforeach
                        </div>

                        @if ($this->design->template->usesAccent())
                            <flux:heading class="mt-6">{{ __('Colour') }}</flux:heading>
                            <div class="mt-3 flex flex-wrap items-center gap-3" role="radiogroup" aria-label="{{ __('Colour') }}">
                                @foreach (\App\Support\CvDesign::ACCENTS as $name => $hex)
                                    <label class="cursor-pointer" title="{{ ucfirst($name) }}">
                                        <input type="radio" wire:model.live="accent" value="{{ $name }}" class="peer sr-only">
                                        <span class="block size-8 rounded-full ring-2 ring-offset-2 ring-offset-canvas transition peer-checked:ring-ink peer-focus-visible:ring-ink {{ $accent === $name ? 'ring-ink' : 'ring-transparent' }}" style="background: {{ $hex }}"></span>
                                        <span class="sr-only">{{ ucfirst($name) }}</span>
                                    </label>
                                @endforeach
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft">
                                    <input type="color" wire:model.live.debounce.400ms="customColour" class="size-8 cursor-pointer rounded-full border-0 bg-transparent p-0 {{ str_starts_with($accent, '#') ? 'ring-2 ring-ink ring-offset-2 ring-offset-canvas' : '' }}">
                                    {{ __('Any colour') }}
                                </label>
                            </div>
                            @error('customColour')
                                <p role="alert" class="mt-2 text-sm text-danger-700 dark:text-danger-300">{{ $message }}</p>
                            @enderror
                        @endif
                    </x-card>

                    {{-- Type and space: steps that scale the template's own
                         sizes together. --}}
                    <x-card as="section" class="space-y-5">
                        <flux:select wire:model.live="font" :label="__('Typeface')" :description="__('Every one has a Bengali partner. PDFs drawn on our own server, when our PDF service is off, use DejaVu instead.')">
                            <flux:select.option value="">{{ __('The template\'s own (:font)', ['font' => $this->design->template->font()->label()]) }}</flux:select.option>
                            @foreach (\App\Enums\CvFont::cases() as $option)
                                <flux:select.option :value="$option->value">{{ $option->label() }}{{ $option->isSerif() ? ' · '.__('serif') : '' }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid gap-5 sm:grid-cols-3">
                            <flux:radio.group wire:model.live="size" :label="__('Text size')" variant="segmented" size="sm">
                                <flux:radio value="small" :label="__('Small')" />
                                <flux:radio value="medium" :label="__('Normal')" />
                                <flux:radio value="large" :label="__('Large')" />
                            </flux:radio.group>
                            <flux:radio.group wire:model.live="spacing" :label="__('Spacing')" variant="segmented" size="sm">
                                <flux:radio value="compact" :label="__('Tight')" />
                                <flux:radio value="normal" :label="__('Normal')" />
                                <flux:radio value="roomy" :label="__('Roomy')" />
                            </flux:radio.group>
                            <flux:radio.group wire:model.live="margins" :label="__('Margins')" variant="segmented" size="sm">
                                <flux:radio value="narrow" :label="__('Narrow')" />
                                <flux:radio value="normal" :label="__('Normal')" />
                                <flux:radio value="wide" :label="__('Wide')" />
                            </flux:radio.group>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-3">
                            <flux:radio.group wire:model.live="headings" :label="__('Headings in')" variant="segmented" size="sm">
                                <flux:radio value="en" label="English" />
                                <flux:radio value="bn" label="বাংলা" />
                            </flux:radio.group>
                            <flux:radio.group wire:model.live="skillLayout" :label="__('Skills as')" variant="segmented" size="sm">
                                <flux:radio value="line" :label="__('One line')" />
                                <flux:radio value="list" :label="__('A list')" />
                            </flux:radio.group>
                            <flux:radio.group wire:model.live="photoShape" :label="__('Photo')" variant="segmented" size="sm">
                                <flux:radio value="square" :label="__('Square')" />
                                <flux:radio value="round" :label="__('Round')" />
                            </flux:radio.group>
                        </div>
                    </x-card>

                    {{-- Sections in order, dragged by the handle or moved with
                         the arrows; each can be opened to leave single
                         entries off this CV. --}}
                    <x-card as="section" padding="none" class="overflow-hidden">
                        <div class="px-5 pt-5 pb-4 sm:px-6">
                            <flux:heading>{{ __('Sections') }}</flux:heading>
                            <flux:text size="sm" class="mt-1">{{ __('Drag to reorder. Untick to leave a section off this CV; open one to leave off single entries.') }}</flux:text>
                        </div>
                        @php
                            $entryKinds = ['experience' => 'experience', 'projects' => 'projects', 'education' => 'education', 'certifications' => 'certifications', 'awards' => 'awards', 'volunteering' => 'volunteering', 'publications' => 'publications'];
                        @endphp
                        <ul class="divide-y divide-line border-t border-line" wire:sort="sortSection">
                            @foreach ($this->design->order as $index => $key)
                                @php
                                    $section = \App\Enums\CvSection::from($key);
                                    $shown = ! in_array($key, $hiddenSections, true);
                                    $kind = $entryKinds[$key] ?? null;
                                    $entries = $kind ? $this->entries[$kind] : [];
                                    $hiddenIds = $kind ? $version->hiddenIds($kind) : [];
                                @endphp
                                <li wire:key="cv-section-{{ $key }}" wire:sort:item="{{ $key }}" x-data="{ open: false }" class="bg-canvas">
                                    <div class="flex items-center gap-3 px-5 py-3 sm:px-6">
                                        <span wire:sort:handle class="cursor-grab text-ink-muted" title="{{ __('Drag to move') }}" aria-hidden="true">
                                            <flux:icon.bars-2 variant="micro" />
                                        </span>
                                        <label class="flex flex-1 cursor-pointer items-center gap-3" wire:sort:ignore>
                                            <input type="checkbox" @checked($shown) wire:click="toggleSection('{{ $key }}')" class="size-4 cursor-pointer rounded accent-current text-ink">
                                            <span @class(['text-sm', 'text-ink' => $shown, 'text-ink-muted line-through' => ! $shown])>{{ __($section->label()) }}</span>
                                            @unless ($section->isFromProfile())
                                                <span class="text-xs text-ink-muted">{{ __('this CV only') }}</span>
                                            @endunless
                                        </label>
                                        <div class="flex items-center" wire:sort:ignore>
                                            @if ($entries !== [])
                                                <flux:button variant="ghost" size="sm" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" :aria-label="__('Choose which :section to show', ['section' => strtolower(__($section->label()))])">
                                                    {{ trans_choice('{0} all :total|[1,*] :shown of :total', count($hiddenIds), ['shown' => count($entries) - count(array_intersect($hiddenIds, array_column($entries, 'id'))), 'total' => count($entries)]) }}
                                                </flux:button>
                                            @endif
                                            <flux:button variant="ghost" size="sm" icon="chevron-up" wire:click="moveSection('{{ $key }}', -1)" :disabled="$index === 0" :aria-label="__('Move :section up', ['section' => __($section->label())])" />
                                            <flux:button variant="ghost" size="sm" icon="chevron-down" wire:click="moveSection('{{ $key }}', 1)" :disabled="$loop->last" :aria-label="__('Move :section down', ['section' => __($section->label())])" />
                                        </div>
                                    </div>
                                    @if ($entries !== [])
                                        <ul x-show="open" x-cloak class="space-y-2 px-5 pb-4 ps-14 sm:px-6 sm:ps-16" wire:sort:ignore>
                                            @foreach ($entries as $entry)
                                                <li wire:key="cv-entry-{{ $kind }}-{{ $entry['id'] }}">
                                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                                        <input type="checkbox" @checked(! in_array($entry['id'], $hiddenIds, true)) wire:click="toggleEntry('{{ $kind }}', {{ $entry['id'] }})" class="size-4 cursor-pointer rounded">
                                                        <span class="text-ink-soft">{{ $entry['label'] }}</span>
                                                    </label>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>

                    <x-card as="section" class="space-y-5">
                        <flux:radio.group wire:model.live="paper" :label="__('Paper')">
                            <flux:radio value="a4" :label="__('A4')" :description="__('Most of the world')" />
                            <flux:radio value="letter" :label="__('US Letter')" :description="__('United States and Canada')" />
                        </flux:radio.group>

                        @if ($this->photo)
                            <flux:switch wire:model.live="withPhoto" :label="__('Include my photo')"
                                :description="__('Usual in much of Europe and Asia. In the US, UK and Canada a CV normally has no photo.')" />
                        @endif
                    </x-card>
                </div>

                {{-- Job & sharing: aiming this CV at a job, and its web page. --}}
                <div id="cv-panel-share" role="tabpanel" aria-labelledby="cv-tab-share" x-show="tab === 'share'" x-cloak class="flex flex-col gap-6">
                    <livewire:cv.tailor :version-id="$versionId" :key="'cv-tailor-'.$versionId" />
                    <livewire:cv.share :version-id="$versionId" :key="'cv-share-'.$versionId" />
                </div>
            </div>

            {{-- Preview: the same view the PDF is drawn from, isolated in a
                 frame so the page's own styles cannot change it. The frame
                 is drawn at the paper's real width and scaled to fit, so
                 the dashed lines fall where the PDF's pages will break.
                 It may read its own document (same origin) to measure it,
                 but runs no scripts. --}}
            <x-card as="section" subtle padding="none" aria-label="{{ __('Preview') }}" x-show="preview" x-cloak class="overflow-hidden p-3 lg:sticky lg:top-6">
                @if ($cv->hasContent())
                    <div
                        x-data="{
                            drawn: false,
                            scale: 1,
                            height: 1123,
                            pages: 1,
                            breaks: [],
                            fitting: false,
                            fitNote: null,
                            paper: @js($paperSize),
                            measure() {
                                const frame = this.$refs.frame
                                const doc = frame?.contentDocument
                                if (! doc || ! doc.body) return
                                const mm = 96 / 25.4
                                const [top, , bottom] = (doc.body.dataset.margins || '0,0,0,0').split(',').map(Number)
                                const pageHeight = this.paper.h * mm
                                const usable = pageHeight - (top + bottom) * mm
                                const content = doc.documentElement.scrollHeight - (top + bottom) * mm
                                this.pages = Math.max(1, Math.ceil(content / usable - 0.02))
                                this.breaks = Array.from({ length: this.pages - 1 }, (_, i) => top * mm + usable * (i + 1))
                                this.height = Math.max(pageHeight, doc.documentElement.scrollHeight)
                                this.scale = Math.min(1, this.$refs.stage.clientWidth / (this.paper.w * mm))
                            },
                            drawnAgain() {
                                this.drawn = true
                                this.measure()
                                // Web fonts can arrive after the frame's load
                                // event and change the page's height.
                                this.$refs.frame?.contentDocument?.fonts?.ready.then(() => this.measure())
                                if (! this.fitting) return
                                if (this.pages <= 1) {
                                    this.fitting = false
                                    this.fitNote = 'fits'
                                    return
                                }
                                this.$wire.tighten().then(more => {
                                    if (! more) {
                                        this.fitting = false
                                        this.fitNote = 'cannot'
                                    }
                                })
                            },
                            fit() {
                                this.fitNote = null
                                if (this.pages <= 1) { this.fitNote = 'fits'; return }
                                this.fitting = true
                                this.$wire.tighten().then(more => {
                                    if (! more) { this.fitting = false; this.fitNote = 'cannot' }
                                })
                            },
                        }"
                        x-init="setTimeout(() => { if (! drawn) { drawn = true; measure() } }, 1500)"
                        x-on:resize.window.debounce.200ms="measure()"
                        class="flex flex-col gap-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                            <span class="text-sm text-ink-muted" aria-live="polite">
                                <span x-text="pages === 1 ? @js(__('1 page')) : pages + ' ' + @js(__('pages'))"></span>
                                <span x-show="fitNote === 'fits'" x-cloak>· {{ __('fits on one page') }}</span>
                                <span x-show="fitNote === 'cannot'" x-cloak>· {{ __('still more than one page at the tightest setting: leave something off') }}</span>
                            </span>
                            <flux:button size="sm" icon="arrows-pointing-in" x-on:click="fit()" x-bind:disabled="fitting">
                                <span x-show="! fitting">{{ __('Fit to one page') }}</span>
                                <span x-show="fitting" x-cloak>{{ __('Fitting…') }}</span>
                            </flux:button>
                        </div>

                        <div x-ref="stage" class="relative w-full overflow-hidden" x-bind:style="`height: ${height * scale}px`">
                            {{-- A page-shaped placeholder until the frame has
                                 drawn the CV, rather than an empty sheet. --}}
                            <div x-show="! drawn" class="absolute inset-0 flex flex-col gap-3 rounded-lg bg-canvas p-8" aria-hidden="true">
                                <flux:skeleton.group animate="shimmer" class="flex flex-col gap-3">
                                    <flux:skeleton class="h-7 w-1/2 rounded" />
                                    <flux:skeleton.line class="w-1/3" />
                                    <flux:skeleton.line class="mt-6" />
                                    <flux:skeleton.line />
                                    <flux:skeleton.line class="w-4/5" />
                                </flux:skeleton.group>
                            </div>
                            <div class="absolute top-0 left-0" x-bind:style="`transform: scale(${scale}); transform-origin: top left; width: ${paper.w}mm; height: ${height}px`">
                                <iframe
                                    x-ref="frame"
                                    srcdoc="{{ $this->preview }}"
                                    sandbox="allow-same-origin"
                                    title="{{ __('Preview of your CV') }}"
                                    x-on:load="drawnAgain()"
                                    :class="{ 'opacity-0': ! drawn }"
                                    class="block size-full rounded-lg bg-white shadow-sm transition-opacity duration-200"
                                ></iframe>
                                <template x-for="(at, i) in breaks" :key="i">
                                    <div class="pointer-events-none absolute inset-x-0 border-t-2 border-dashed border-danger-400" x-bind:style="`top: ${at}px`" aria-hidden="true">
                                        <span class="absolute right-2 -top-5 rounded bg-danger-50 px-1.5 text-xs text-danger-700" x-text="@js(__('Page')) + ' ' + (i + 2)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                @else
                    <x-empty-state icon="document-text" :heading="__('Your CV appears here')">
                        {{ __('Add a role, a project, a course or a skill on the left and the page fills in as you go.') }}
                    </x-empty-state>
                @endif
            </x-card>
        </div>

            @if ($aiStatus === 'done' && ($shown = $this->shownSuggestions))
                @php
                    $profile = auth()->user()->candidateProfile;
                @endphp
                <x-card as="section" class="mt-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">{{ __('AI suggestions') }}</flux:heading>
                        <x-chip>{{ __('AI-generated — check every line') }}</x-chip>
                    </div>
                    <flux:text size="sm" class="mt-1">{{ __('Ticked suggestions replace that part of your profile, and the CV follows. Anything with a number you did not write starts unticked.') }}</flux:text>
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-3">
                        <flux:callout.text>
                            {{ __('Before you apply a suggestion, check that every claim in it is true, not only the flagged numbers. The AI can add a result you never mentioned, such as "improving team velocity", or join two separate things into one claim. Untick anything you could not explain in an interview.') }}
                        </flux:callout.text>
                    </flux:callout>

                    <div class="mt-5 space-y-6">
                        @foreach (['headline' => [__('Headline'), $shown->headline, 'useHeadline', $shown->headlineIsCurrent($profile), $profile->headline], 'summary' => [__('Summary'), $shown->summary, 'useSummary', $shown->summaryIsCurrent($profile), $profile->bio]] as $part => [$label, $item, $model, $current, $now])
                            @if ($item)
                                <div wire:key="suggestion-{{ $part }}">
                                    @if ($current)
                                        <flux:checkbox wire:model="{{ $model }}" :label="__('Use this :part', ['part' => strtolower($label)])" />
                                    @else
                                        <p class="text-sm font-medium text-ink">{{ $label }}</p>
                                        <p class="text-sm text-ink-muted">{{ __('You changed this since the AI read it, so your own text stays.') }}</p>
                                    @endif
                                    <div class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('Now') }}</p>
                                            <p class="mt-1 whitespace-pre-line text-ink-muted">{{ filled($now) ? $now : __('Nothing yet') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('Suggested') }}</p>
                                            <p class="mt-1 whitespace-pre-line text-ink">{{ $item['suggested'] }}</p>
                                        </div>
                                    </div>
                                    @if ($item['new_numbers'] !== [])
                                        <p class="mt-2 text-sm text-warning-700 dark:text-warning-300">{{ __('Check these numbers, which your profile does not give: :numbers.', ['numbers' => implode(', ', $item['new_numbers'])]) }}</p>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        @foreach ($shown->experience as $role)
                            @php
                                $record = $this->roles->get($role['id']);
                            @endphp
                            <div wire:key="suggestion-role-{{ $role['id'] }}">
                                @if ($role['too_long'])
                                    <p class="text-sm font-medium text-ink">{{ __(':title at :company', ['title' => $role['title'], 'company' => $role['company']]) }}</p>
                                    <p class="text-sm text-ink-muted">{{ __('These bullet points are longer than a role description can be (:max characters), so they cannot be applied.', ['max' => number_format(\App\Support\CvSuggestions::DESCRIPTION_MAX)]) }}</p>
                                @elseif (! \App\Support\CvSuggestions::roleIsCurrent($role, $record))
                                    <p class="text-sm font-medium text-ink">{{ __(':title at :company', ['title' => $role['title'], 'company' => $role['company']]) }}</p>
                                    <p class="text-sm text-ink-muted">{{ __('You changed or removed this role since the AI read it, so your own text stays.') }}</p>
                                @else
                                    <flux:checkbox wire:model="useRoles" value="{{ $role['id'] }}" :label="__('Use these bullet points for :title at :company', ['title' => $role['title'], 'company' => $role['company']])" />
                                @endif
                                <div class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('Now') }}</p>
                                        <div class="prose-content mt-1 text-ink-muted">{!! $record?->description ?: e(__('Nothing yet')) !!}</div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('Suggested') }}</p>
                                        <ul class="mt-1 list-disc space-y-1 pl-5 text-ink">
                                            @foreach ($role['bullets'] as $bullet)
                                                <li>{{ $bullet }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                @if ($role['new_numbers'] !== [])
                                    <p class="mt-2 text-sm text-warning-700 dark:text-warning-300">{{ __('Check these numbers, which this role\'s description does not give: :numbers.', ['numbers' => implode(', ', $role['new_numbers'])]) }}</p>
                                @endif
                            </div>
                        @endforeach

                        @if ($shown->tips !== [])
                            <div>
                                <p class="text-sm font-medium text-ink">{{ __('Tips') }}</p>
                                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-ink-soft">
                                    @foreach ($shown->tips as $tip)
                                        <li>{{ $tip }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <flux:button wire:click="discardSuggestions" variant="ghost">{{ __('Discard suggestions') }}</flux:button>
                        @if ($shown->headline || $shown->summary || $shown->experience !== [])
                            <flux:button wire:click="applySuggestions" variant="primary">{{ __('Apply to my profile') }}</flux:button>
                        @endif
                    </div>
                </x-card>
            @endif

        {{-- The dialogs behind the pencils in "Contact and summary". They
             send the page back here once saved. --}}
        @include('candidate.profile.partials.dialogs', ['profile' => auth()->user()->candidateProfile, 'user' => auth()->user()])
    </div>
</x-page>
