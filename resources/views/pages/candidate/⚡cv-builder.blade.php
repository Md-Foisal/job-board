<?php

use App\Actions\RenderCvPdf;
use App\Actions\StoreCandidateDocument;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\CvChecks;
use App\Support\CvData;
use App\Support\DocumentUploads;
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

    public function updated(): void
    {
        $this->savedDocumentId = null;
        $this->buildFailed = false;
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
            $this->addError('build', __("You've built :count CVs in the last hour. Try again in :minutes minutes.", [
                'count' => self::BUILDS_PER_HOUR,
                'minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
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
        <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Nothing to build a CV from yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Add at least one role, course or skill to your profile, and your CV appears here.') }}</flux:text>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <flux:button :href="route('candidate.experience.index')" wire:navigate size="sm">{{ __('Add experience') }}</flux:button>
                <flux:button :href="route('candidate.education.index')" wire:navigate size="sm">{{ __('Add education') }}</flux:button>
                <flux:button :href="route('candidate.skills.edit')" wire:navigate size="sm">{{ __('Add skills') }}</flux:button>
                <flux:button :href="route('candidate.documents.index')" wire:navigate size="sm" variant="ghost">{{ __('Fill your profile from a CV') }}</flux:button>
            </div>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="space-y-6">
                {{-- What is missing --}}
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    @if ($checks === [])
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" variant="mini" class="text-green-600 dark:text-green-400" />
                            <flux:heading>{{ __('Nothing missing') }}</flux:heading>
                        </div>
                        <flux:text size="sm" class="mt-1">{{ __('Every part of a CV has something in it.') }}</flux:text>
                    @else
                        <flux:heading>{{ __('Worth adding') }}</flux:heading>
                        <flux:text size="sm">{{ __('Optional. You can build your CV without them.') }}</flux:text>
                        <ul class="mt-3 space-y-2">
                            @foreach ($checks as $check)
                                <li wire:key="check-{{ $loop->index }}" class="flex items-start justify-between gap-3 text-sm">
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $check['gap']->message($check['subject']) }}</span>
                                    <a href="{{ route($check['gap']->route()) }}" wire:navigate class="shrink-0 font-medium text-brand-700 hover:underline dark:text-brand-400">{{ __('Add') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if ($unsupported !== [])
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:callout.heading>{{ __("Some text may not show correctly in the PDF") }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Text in your :parts is in a script our PDF maker cannot draw properly yet, such as Bengali or Arabic. The preview here is right, but in the PDF it may show as empty boxes or with letters out of order. Writing those parts in English avoids it.', ['parts' => collect($unsupported)->map(fn ($part) => strtolower(__($part)))->join(', ', ' '.__('and').' ')]) }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Options --}}
                <section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:radio.group wire:model.live="paper" :label="__('Paper')">
                        <flux:radio value="a4" :label="__('A4')" :description="__('Most of the world')" />
                        <flux:radio value="letter" :label="__('US Letter')" :description="__('United States and Canada')" />
                    </flux:radio.group>

                    @if ($this->photo)
                        <flux:switch wire:model.live="withPhoto" :label="__('Include my photo')"
                            :description="__('Usual in much of Europe and Asia. In the US, UK and Canada a CV normally has no photo.')" />
                    @endif
                </section>

                {{-- Save or download --}}
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap gap-2">
                        {{-- Flux shows a spinner and ignores clicks while each runs. --}}
                        <flux:button wire:click="save" variant="primary" icon="document-plus">{{ __('Save to my CVs') }}</flux:button>
                        <flux:button wire:click="download" icon="arrow-down-tray">{{ __('Download PDF') }}</flux:button>
                    </div>

                    <flux:text size="sm" class="mt-3">
                        {{ __('Saving keeps it in your documents, ready to send with an application. Downloading only gives you the file.') }}
                    </flux:text>

                    @if ($this->leavingCv && ! $this->savedDocument)
                        <flux:text size="sm" class="mt-2 text-amber-700 dark:text-amber-400">
                            {{ __('You keep your :count most recent CVs, so saving moves your oldest, :name, out of your library. Applications already sent with it keep it.', ['count' => \App\Support\DocumentUploads::RECENT_CVS_KEPT, 'name' => $this->leavingCv->original_filename]) }}
                        </flux:text>
                    @endif

                    @if ($this->savedDocument)
                        <div role="status" class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                            <flux:icon name="check-circle" variant="mini" />
                            <span>{{ __('Saved to your CVs as :name.', ['name' => $this->savedDocument->original_filename]) }}</span>
                            <a href="{{ route('candidate.documents.download', $this->savedDocument) }}" class="font-medium underline">{{ __('Download') }}</a>
                            <a href="{{ route('candidate.documents.index') }}" wire:navigate class="font-medium underline">{{ __('View documents') }}</a>
                        </div>
                    @endif

                    @if ($buildFailed)
                        <p role="alert" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ __("We couldn't build the PDF. Nothing was saved; please try again.") }}</p>
                    @endif

                    @error('build')
                        <p role="alert" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>
            </div>

            {{-- Preview: the same view the PDF is drawn from, isolated in a
                 frame so the page's own styles cannot change it. --}}
            <section aria-label="{{ __('Preview') }}" class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 p-3 dark:border-zinc-700 dark:bg-zinc-950">
                <iframe
                    srcdoc="{{ $this->preview }}"
                    sandbox=""
                    title="{{ __('Preview of your CV') }}"
                    class="mx-auto block h-[80vh] w-full max-w-[210mm] rounded-lg bg-white shadow-sm"
                ></iframe>
            </section>
        </div>
    @endif
</div>
