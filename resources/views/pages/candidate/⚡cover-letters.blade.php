<?php

use App\Actions\RenderCvPdf;
use App\Exceptions\CvPdfUnavailable;
use App\Models\CoverLetter;
use App\Models\JobPosting;
use App\Support\CoverLetterDraft;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvVersions;
use App\Support\DateFormat;
use App\Support\LocalTime;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\LaravelPdf\Enums\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Cover letters, each aimed at one job: a job on the board the candidate
 * applied to or saved, or a company and role typed in for a job
 * elsewhere. A first draft can be written from the profile and the job
 * by our own rules (CoverLetterDraft); the candidate makes it their own.
 * The letter prints in the look of one of their saved CVs, so the two
 * match, and fills the application form's cover letter for that job.
 *
 * Opened with ?job={slug} from a job or the application form, it goes
 * to the letter for that job, making it the first time.
 */
new #[Layout('layouts::app')] #[Title('Cover letters')] class extends Component {
    #[Locked]
    public ?int $letterId = null;

    /** The job on the board, by id as a string for the select; '' for none. */
    public string $jobId = '';

    public string $companyName = '';

    public string $roleTitle = '';

    public string $recipient = '';

    public string $body = '';

    /** The saved CV whose look the letter takes, '' for the latest. */
    public string $cvVersionId = '';

    public string $paper = 'a4';

    public function mount(): void
    {
        $profile = auth()->user()->candidateProfile;
        $slug = request()->query('job');
        $letter = null;

        if (is_string($slug) && $job = JobPosting::query()->where('slug', $slug)->first()) {
            $letter = $profile->coverLetters()->where('job_posting_id', $job->id)->latest('id')->first()
                ?? ($this->jobs->contains('id', $job->id) ? $this->createLetter(['job_posting_id' => $job->id]) : null);
        }

        $requested = request()->query('letter');
        $letter ??= is_numeric($requested) ? $profile->coverLetters()->find((int) $requested) : null;
        $letter ??= $profile->coverLetters()->latest('updated_at')->first();

        if ($letter) {
            $this->load($letter);
        }
    }

    public function updated(string $property): void
    {
        if ($this->letterId === null || ! in_array($property, ['jobId', 'companyName', 'roleTitle', 'recipient', 'body', 'cvVersionId'], true)) {
            return;
        }

        $this->validate($this->rules(), [], [
            'jobId' => __('job'),
            'companyName' => __('company'),
            'roleTitle' => __('role'),
            'body' => __('letter'),
        ]);

        $this->letter->update([
            'job_posting_id' => $this->jobId === '' ? null : (int) $this->jobId,
            'company_name' => trim($this->companyName) ?: null,
            'role_title' => trim($this->roleTitle) ?: null,
            'recipient' => trim($this->recipient) ?: null,
            'body' => $this->body,
            'cv_version_id' => $this->cvVersionId === '' ? null : (int) $this->cvVersionId,
        ]);

        unset($this->letter, $this->letters, $this->preview);
    }

    public function newLetter(): void
    {
        if (auth()->user()->candidateProfile->coverLetters()->count() >= CoverLetter::MAX_PER_PROFILE) {
            Flux::toast(variant: 'warning', text: __('You can keep :count cover letters. Delete one you no longer use to write another.', ['count' => CoverLetter::MAX_PER_PROFILE]));

            return;
        }

        $this->load($this->createLetter([]));
    }

    public function open(int $id): void
    {
        $letter = auth()->user()->candidateProfile->coverLetters()->find($id);

        if ($letter) {
            $this->load($letter);
        }
    }

    public function deleteLetter(): void
    {
        if ($this->letterId === null) {
            return;
        }

        $this->letter->delete();
        $this->letterId = null;
        unset($this->letters);

        if ($next = auth()->user()->candidateProfile->coverLetters()->latest('updated_at')->first()) {
            $this->load($next);
        }
    }

    /**
     * A first draft from the profile and the job, in place of what is
     * there.
     */
    public function draft(): void
    {
        if ($this->letterId === null) {
            return;
        }

        $letter = $this->letter;
        $this->body = mb_substr(CoverLetterDraft::write(auth()->user()->candidateProfile, $letter->jobPosting, $this->companyName, $this->roleTitle, $this->recipient), 0, CoverLetter::BODY_MAX);
        $letter->update(['body' => $this->body]);
        unset($this->letter, $this->preview);
    }

    public function download(): ?StreamedResponse
    {
        if ($this->letterId === null || trim($this->body) === '') {
            return null;
        }

        $key = 'cv-build:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('build', trans_choice("{1} You've made 10 PDFs in the last hour. Try again in 1 minute.|[2,*] You've made 10 PDFs in the last hour. Try again in :count minutes.", SubmissionLimits::minutesUntilAvailable($key)));

            return null;
        }

        RateLimiter::hit($key, 60 * 60);

        try {
            $pdf = app(RenderCvPdf::class)($this->letterhead(), false, $this->paper === 'letter' ? Format::Letter : Format::A4, $this->look(), 'cv.cover-letter', [
                'letter' => $this->letter,
                'date' => $this->date(),
            ]);
        } catch (CvPdfUnavailable) {
            $this->addError('build', __('Your letter has text that only our PDF service can draw, and it could not be reached just now. Try again in a few minutes.'));

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('build', __("We couldn't make the PDF. Please try again."));

            return null;
        }

        $name = str_replace('-CV.pdf', '-Cover-Letter.pdf', $this->letterhead()->fileName());

        return response()->streamDownload(fn () => print ($pdf), $name === 'CV.pdf' ? 'Cover-Letter.pdf' : $name, ['Content-Type' => 'application/pdf']);
    }

    #[Computed]
    public function letter(): CoverLetter
    {
        return auth()->user()->candidateProfile->coverLetters()->with('jobPosting.company')->findOrFail($this->letterId);
    }

    #[Computed]
    public function letters()
    {
        return auth()->user()->candidateProfile->coverLetters()->with('jobPosting.company')->latest('updated_at')->get();
    }

    /**
     * The jobs a letter can be aimed at: ones applied to or saved, newest
     * first.
     */
    #[Computed]
    public function jobs()
    {
        $profile = auth()->user()->candidateProfile;
        $applied = JobPosting::query()->whereIn('id', $profile->applications()->select('job_posting_id'))->with('company:id,name')->get(['id', 'title', 'company_id', 'slug']);
        $saved = auth()->user()->savedJobs()->with('company:id,name')->get(['job_postings.id', 'job_postings.title', 'job_postings.company_id', 'job_postings.slug']);

        return $applied->concat($saved)->unique('id')->values();
    }

    #[Computed]
    public function versions()
    {
        return auth()->user()->candidateProfile->cvVersions()->latest('updated_at')->get(['id', 'name']);
    }

    #[Computed]
    public function preview(): string
    {
        return view('cv.cover-letter', [
            'cv' => $this->letterhead(),
            'design' => $this->look(),
            'letter' => $this->letter,
            'date' => $this->date(),
            'photo' => null,
            'preview' => true,
            'fonts' => 'url',
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $jobIds = $this->jobs->pluck('id')->map(fn ($id) => (string) $id)->all();

        return [
            'jobId' => ['nullable', 'in:'.implode(',', ['', ...$jobIds])],
            'companyName' => ['string', 'max:255'],
            'roleTitle' => ['string', 'max:255'],
            'recipient' => ['string', 'max:255'],
            'body' => ['string', 'max:'.CoverLetter::BODY_MAX],
            'cvVersionId' => ['nullable', 'in:'.implode(',', ['', ...$this->versions->pluck('id')->map(fn ($id) => (string) $id)->all()])],
        ];
    }

    private function createLetter(array $attributes): CoverLetter
    {
        $letter = auth()->user()->candidateProfile->coverLetters()->create([...$attributes, 'body' => '']);
        $letter->update(['body' => mb_substr(CoverLetterDraft::write(auth()->user()->candidateProfile, $letter->jobPosting, null, null, null), 0, CoverLetter::BODY_MAX)]);
        unset($this->letters);

        return $letter;
    }

    private function load(CoverLetter $letter): void
    {
        $this->letterId = $letter->id;
        $this->jobId = $letter->job_posting_id ? (string) $letter->job_posting_id : '';
        $this->companyName = (string) $letter->company_name;
        $this->roleTitle = (string) $letter->role_title;
        $this->recipient = (string) $letter->recipient;
        $this->body = (string) $letter->body;
        $this->cvVersionId = $letter->cv_version_id ? (string) $letter->cv_version_id : '';
        $this->resetValidation();
        unset($this->letter, $this->preview);
    }

    /**
     * The look of the CV the letter goes with: the one chosen, or the CV
     * worked on most recently.
     */
    private function look(): CvDesign
    {
        return $this->lookVersion()->cvDesign();
    }

    private function letterhead(): CvData
    {
        return CvData::fromProfile(auth()->user()->candidateProfile, $this->lookVersion());
    }

    private function lookVersion()
    {
        $profile = auth()->user()->candidateProfile;

        return ($this->cvVersionId !== '' ? $profile->cvVersions()->find((int) $this->cvVersionId) : null) ?? CvVersions::open($profile);
    }

    private function date(): string
    {
        return LocalTime::of(now())->format('j F Y');
    }
}; ?>

<x-page>
    <div class="flex flex-col gap-6">
        <x-page-header :title="__('Cover letters')">
            <x-slot:actions>
                <flux:button :href="route('candidate.cv-builder')" wire:navigate icon="document-plus">{{ __('CV builder') }}</flux:button>
                <flux:button wire:click="newLetter" variant="primary" class="btn-sunset" icon="plus">{{ __('New cover letter') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        @if ($letterId === null)
            <x-card>
                <x-empty-state icon="envelope" :heading="__('No cover letters yet')">
                    {{ __('Write one for a job you applied to or saved, or for a job elsewhere. We start it from your profile and the job; you make it yours.') }}
                    <x-slot:actions>
                        <flux:button wire:click="newLetter" variant="primary" size="sm" icon="plus">{{ __('New cover letter') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </x-card>
        @else
            @error('build')
                <p role="alert" class="text-sm text-danger-700 dark:text-danger-300">{{ $message }}</p>
            @enderror

            <div class="grid items-start gap-6 lg:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-6">
                    @if ($this->letters->count() > 1)
                        <x-card as="section" padding="sm">
                            <flux:select :label="__('Letter')" x-on:change="$wire.open(Number($event.target.value))">
                                @foreach ($this->letters as $option)
                                    <flux:select.option :value="$option->id" :selected="$option->id === $letterId">
                                        {{ collect([$option->roleTitle(), $option->companyName()])->filter()->implode(' · ') ?: __('Untitled letter') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </x-card>
                    @endif

                    <x-card as="section" class="space-y-5">
                        <flux:select wire:model.live="jobId" :label="__('For a job on :app', ['app' => config('app.name')])" :description="__('Jobs you applied to or saved.')">
                            <flux:select.option value="">{{ __('A job elsewhere') }}</flux:select.option>
                            @foreach ($this->jobs as $job)
                                <flux:select.option :value="(string) $job->id">{{ $job->title }} · {{ $job->company?->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($jobId === '')
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model.blur="companyName" :label="__('Company')" maxlength="255" />
                                <flux:input wire:model.blur="roleTitle" :label="__('Role')" maxlength="255" />
                            </div>
                        @endif

                        <flux:input wire:model.blur="recipient" :label="__('Addressed to')" :description="__('The hiring manager\'s name, if the job ad gives one. Otherwise the letter opens with “Dear Hiring Team”.')" maxlength="255" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model.live="cvVersionId" :label="__('Look')" :description="__('The typeface and colour of one of your CVs.')">
                                <flux:select.option value="">{{ __('My latest CV') }}</flux:select.option>
                                @foreach ($this->versions as $version)
                                    <flux:select.option :value="(string) $version->id">{{ $version->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:radio.group wire:model.live="paper" :label="__('Paper')" variant="segmented" size="sm">
                                <flux:radio value="a4" :label="__('A4')" />
                                <flux:radio value="letter" :label="__('US Letter')" />
                            </flux:radio.group>
                        </div>
                    </x-card>

                    <x-card as="section" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <flux:heading>{{ __('The letter') }}</flux:heading>
                            <flux:button wire:click="draft" wire:confirm="{{ __('Replace the letter with a new draft from your profile and the job?') }}" size="sm" icon="arrow-path">{{ __('Start again from my profile') }}</flux:button>
                        </div>
                        <flux:text size="sm">{{ __('A starting draft built from what your profile says, with nothing added. Put it in your own words: say why this company, and what you would bring.') }}</flux:text>
                        <flux:textarea wire:model.blur="body" :label="__('Letter')" rows="16" maxlength="{{ \App\Models\CoverLetter::BODY_MAX }}" :description="__('A blank line starts a new paragraph.')" />
                        <div class="flex flex-wrap gap-2">
                            <flux:button wire:click="download" variant="primary" icon="arrow-down-tray">{{ __('Download PDF') }}</flux:button>
                            <flux:button x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText($wire.body).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" icon="clipboard">
                                <span x-show="! copied">{{ __('Copy text') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                            </flux:button>
                            @if ($this->letter->jobPosting)
                                <flux:button :href="route('jobs.apply', $this->letter->jobPosting)" wire:navigate icon="paper-airplane">{{ __('Apply with it') }}</flux:button>
                            @endif
                            <flux:button wire:click="deleteLetter" wire:confirm="{{ __('Delete this cover letter?') }}" variant="ghost" icon="trash" class="ms-auto">{{ __('Delete') }}</flux:button>
                        </div>
                    </x-card>
                </div>

                <x-card as="section" subtle padding="none" aria-label="{{ __('Preview') }}" class="overflow-hidden p-3 lg:sticky lg:top-6">
                    <iframe
                        srcdoc="{{ $this->preview }}"
                        sandbox=""
                        title="{{ __('Preview of your cover letter') }}"
                        class="mx-auto block h-[calc(100dvh-10rem)] min-h-[32rem] w-full max-w-[210mm] rounded-lg bg-white shadow-sm"
                    ></iframe>
                </x-card>
            </div>
        @endif
    </div>
</x-page>
