<?php

use App\Actions\ApplyCvSuggestions;
use App\Actions\RenderCvPdf;
use App\Actions\StoreCandidateDocument;
use App\Ai\Agents\CvWriter;
use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\DocumentType;
use App\Jobs\PolishCvWithAi;
use App\Models\Document;
use App\Models\ExperienceRecord;
use App\Support\AiQuota;
use App\Support\CvChecks;
use App\Support\CvData;
use App\Support\CvSuggestions;
use App\Support\DocumentUploads;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\LaravelPdf\Enums\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app')] #[Title('CV Builder')] class extends Component {
    /**
     * Building a PDF is the heaviest thing a candidate can ask the server
     * for, so saving and downloading share an hourly allowance.
     */
    public const BUILDS_PER_HOUR = 10;

    public bool $withPhoto = false;

    public string $paper = 'a4';

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
     * Suggestions written within the last hour are shown again rather
     * than asked for, and paid for, twice.
     */
    public function mount(): void
    {
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
        if (in_array($property, ['withPhoto', 'paper'], true)) {
            $this->savedDocumentId = null;
            $this->buildFailed = false;
        }
    }

    #[Computed]
    public function cv(): CvData
    {
        return CvData::fromProfile(auth()->user()->candidateProfile);
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
    public function photo(): ?string
    {
        return $this->cv->photo();
    }

    #[Computed]
    public function preview(): string
    {
        return view('cv.classic', [
            'cv' => $this->cv,
            'photo' => $this->withPhoto ? $this->photo : null,
            'preview' => true,
        ])->render();
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
        unset($this->cv, $this->checks, $this->preview, $this->shownSuggestions, $this->roles);
        $this->savedDocumentId = null;
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
            return app(RenderCvPdf::class)($this->cv, $this->withPhoto && $this->photo !== null, Format::from($this->paper));
        } catch (Throwable $exception) {
            report($exception);
            $this->buildFailed = true;

            return null;
        }
    }
}; ?>

@php
    $cv = $this->cv;
    $checks = $this->checks;
    $unsupported = $cv->unsupportedScriptParts();
@endphp

<div class="mx-auto max-w-6xl px-6 py-10">
    <flux:heading size="xl" level="1">{{ __('CV Builder') }}</flux:heading>
    <flux:subheading>{{ __('A CV made from your profile. Change your profile and the CV follows.') }}</flux:subheading>

    @if (! $cv->hasContent())
        <x-empty-state icon="document-text" class="mt-6" :heading="__('Nothing to build a CV from yet')">
            {{ __('Add at least one role, course or skill to your profile, and your CV appears here.') }}
            <x-slot:actions>
                <flux:button :href="route('candidate.experience.index')" wire:navigate size="sm">{{ __('Add experience') }}</flux:button>
                <flux:button :href="route('candidate.education.index')" wire:navigate size="sm">{{ __('Add education') }}</flux:button>
                <flux:button :href="route('candidate.skills.edit')" wire:navigate size="sm">{{ __('Add skills') }}</flux:button>
                <flux:button :href="route('candidate.documents.index')" wire:navigate size="sm" variant="ghost">{{ __('Fill your profile from a CV') }}</flux:button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="space-y-6">
                {{-- What is missing --}}
                <x-card as="section">
                    @if ($checks === [])
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" variant="mini" class="text-success-500" />
                            <flux:heading>{{ __('Nothing missing') }}</flux:heading>
                        </div>
                        <flux:text size="sm" class="mt-1">{{ __('Every part of a CV has something in it.') }}</flux:text>
                    @else
                        <flux:heading>{{ __('Worth adding') }}</flux:heading>
                        <flux:text size="sm">{{ __('Optional. You can build your CV without them.') }}</flux:text>
                        <ul class="mt-3 space-y-2">
                            @foreach ($checks as $check)
                                <li wire:key="check-{{ $loop->index }}" class="flex items-start justify-between gap-3 text-sm">
                                    <span class="text-ink-soft">{{ $check['gap']->message($check['subject']) }}</span>
                                    <a href="{{ route($check['gap']->route()) }}" wire:navigate class="shrink-0 font-medium text-sunset-small hover:underline">{{ __('Add') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                @if ($unsupported !== [])
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:callout.heading>{{ __("Some text may not show correctly in the PDF") }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Text in your :parts is in a script our PDF maker cannot draw properly yet, such as Bengali or Arabic. The preview here is right, but in the PDF it may show as empty boxes or with letters out of order. Writing those parts in English avoids it.', ['parts' => collect($unsupported)->map(fn ($part) => strtolower(__($part)))->join(', ', ' '.__('and').' ')]) }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Options --}}
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

                {{-- Save or download --}}
                <x-card as="section">
                    <div class="flex flex-wrap gap-2">
                        {{-- Flux shows a spinner and ignores clicks while each runs. --}}
                        <flux:button wire:click="save" variant="primary" icon="document-plus">{{ __('Save to my CVs') }}</flux:button>
                        <flux:button wire:click="download" icon="arrow-down-tray">{{ __('Download PDF') }}</flux:button>
                    </div>

                    <flux:text size="sm" class="mt-3">
                        {{ __('Saving keeps it in your documents, ready to send with an application. Downloading only gives you the file.') }}
                    </flux:text>

                    @if ($this->leavingCv && ! $this->savedDocument)
                        <flux:text size="sm" class="mt-2 text-warning-700 dark:text-warning-300">
                            {{ __('You keep your :count most recent CVs, so saving moves your oldest, :name, out of your library. Applications already sent with it keep it.', ['count' => \App\Support\DocumentUploads::RECENT_CVS_KEPT, 'name' => $this->leavingCv->original_filename]) }}
                        </flux:text>
                    @endif

                    @if ($this->savedDocument)
                        <div role="status" class="mt-4 flex flex-wrap items-center gap-2 rounded-control bg-success-50 p-3 text-sm text-success-700 dark:bg-success-950 dark:text-success-300">
                            <flux:icon name="check-circle" variant="mini" />
                            <span>{{ __('Saved to your CVs as :name.', ['name' => $this->savedDocument->original_filename]) }}</span>
                            <a href="{{ route('candidate.documents.download', $this->savedDocument) }}" class="font-medium underline">{{ __('Download') }}</a>
                            <a href="{{ route('candidate.documents.index') }}" wire:navigate class="font-medium underline">{{ __('View documents') }}</a>
                        </div>
                    @endif

                    @if ($buildFailed)
                        <p role="alert" class="mt-4 text-sm text-danger-700 dark:text-danger-300">{{ __("We couldn't build the PDF. Nothing was saved; please try again.") }}</p>
                    @endif

                    @error('build')
                        <p role="alert" class="mt-4 text-sm text-danger-700 dark:text-danger-300">{{ $message }}</p>
                    @enderror
                </x-card>

                {{-- AI suggestions: offered, running, failed, or out of allowance.
                     With AI off, or a plan without it, nothing shows here. --}}
                @php($availability = $this->aiAvailability)

                @if ($aiStatus === 'running')
                    <x-ai-working wire:poll.2s="checkAi" :heading="__('Writing suggestions…')">{{ __('This usually takes under a minute. You can stay on this page.') }}</x-ai-working>
                @elseif ($aiStatus === 'done')
                    <flux:text size="sm">{{ __('The AI suggestions are below the preview.') }}</flux:text>
                @elseif ($aiStatus === 'unavailable' || ($aiStatus === null && $availability === \App\Enums\AiAvailability::LimitReached))
                    <flux:text size="sm">
                        {{ __("You've used this month's AI suggestions. They reset on :date.", ['date' => \App\Support\LocalTime::of(now()->startOfMonth()->addMonth())->format(\App\Support\DateFormat::MOMENT)]) }}
                    </flux:text>
                @elseif ($availability === \App\Enums\AiAvailability::Available && in_array($aiStatus, [null, 'failed'], true))
                    <x-card as="section">
                        @if ($aiStatus === 'failed')
                            <flux:heading>{{ __("The AI couldn't write suggestions this time") }}</flux:heading>
                            <flux:text size="sm" class="mt-1">{{ __('You can try again. Your CV above does not depend on it.') }}</flux:text>
                        @else
                            <flux:heading>{{ __('Improve the wording with AI') }}</flux:heading>
                            <flux:text size="sm" class="mt-1">{{ __('Suggests a headline, a summary and bullet points for your roles, from what your profile already says. You choose what goes into your profile.') }}</flux:text>
                        @endif
                        <flux:button wire:click="polish" icon="sparkles" size="sm" class="mt-3">
                            {{ $aiStatus === 'failed' ? __('Try again') : __('Improve with AI') }}
                        </flux:button>
                        <flux:text size="sm" class="mt-2 text-ink-muted">
                            {{ __("Your headline, summary, roles, education and skills (not your name, contact details, links or photo) are sent to Anthropic. Anthropic doesn't train on them and, by default, deletes them within 30 days.") }}
                        </flux:text>
                    </x-card>
                @endif
            </div>

            {{-- Preview: the same view the PDF is drawn from, isolated in a
                 frame so the page's own styles cannot change it. --}}
            <x-card as="section" subtle padding="none" aria-label="{{ __('Preview') }}" class="overflow-hidden p-3">
                <iframe
                    srcdoc="{{ $this->preview }}"
                    sandbox=""
                    title="{{ __('Preview of your CV') }}"
                    class="mx-auto block h-[80vh] w-full max-w-[210mm] rounded-lg bg-white shadow-sm"
                ></iframe>
            </x-card>
        </div>

        @if ($aiStatus === 'done' && ($shown = $this->shownSuggestions))
            @php($profile = auth()->user()->candidateProfile)
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
                        @php($record = $this->roles->get($role['id']))
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
    @endif
</div>
