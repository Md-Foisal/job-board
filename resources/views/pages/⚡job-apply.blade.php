<?php

use App\Actions\StoreCandidateDocument;
use App\Enums\DocumentType;
use App\Events\ApplicationSubmitted;
use App\Models\Application;
use App\Models\JobPosting;
use App\Support\DocumentUploads;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::guest')] class extends Component {
    use WithFileUploads;

    public JobPosting $jobPosting;

    public Collection $existingCvs;

    public string $resumeChoice = 'new';

    public $newResume = null;

    public string $coverLetter = '';

    public array $screeningAnswers = [];

    public function mount(JobPosting $jobPosting): void
    {
        // A hidden posting stays a 404, as on its own page; every other
        // refusal has an explanation waiting on the job page.
        $access = Gate::inspect('create', [Application::class, $jobPosting]);

        if ($access->status() === 404) {
            abort(404);
        }

        if ($access->denied()) {
            $this->redirectRoute('jobs.show', $jobPosting);

            return;
        }

        // The columns the summary beside the form reads: the logo tile, and
        // the zone the closing day is counted in.
        $jobPosting->load(['company:id,name,slug,logo_path,verified_at,timezone', 'screeningQuestions']);
        $this->jobPosting = $jobPosting;

        $this->existingCvs = auth()->user()->candidateProfile->documents()
            ->where('document_type', DocumentType::Cv)
            ->latest()
            ->latest('id')
            ->get();

        $this->resumeChoice = $this->existingCvs->isNotEmpty()
            ? (string) $this->existingCvs->first()->id
            : 'new';

        foreach ($this->jobPosting->screeningQuestions as $question) {
            $this->screeningAnswers[$question->id] = '';
        }
    }

    public function submit(): void
    {
        $this->authorize('create', [Application::class, $this->jobPosting]);

        $limitKey = SubmissionLimits::applicationKey(auth()->user());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::APPLICATIONS_PER_DAY)) {
            Flux::toast(
                variant: 'warning',
                duration: 10000,
                heading: __("You've reached today's application limit"),
                text: trans_choice('{1} We cap applications at :limit a day so each one gets proper attention. You can apply again in 1 hour.|[2,*] We cap applications at :limit a day so each one gets proper attention. You can apply again in :count hours.', SubmissionLimits::hoursUntilAvailable($limitKey), [
                    'limit' => SubmissionLimits::APPLICATIONS_PER_DAY,
                ]),
            );

            return;
        }

        $rules = [
            'resumeChoice' => [
                'required',
                Rule::in(array_merge(['new'], $this->existingCvs->pluck('id')->map(fn ($id) => (string) $id)->all())),
            ],
            'coverLetter' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->resumeChoice === 'new') {
            $rules['newResume'] = DocumentUploads::rules(DocumentType::Cv);
        }

        foreach ($this->jobPosting->screeningQuestions as $question) {
            $rules["screeningAnswers.{$question->id}"] = ['required', 'string', 'max:2000'];
        }

        $this->validate($rules, [
            'newResume.required' => __('Choose a CV to upload, or pick one you have already uploaded.'),
            'screeningAnswers.*.required' => __('Answer this question to apply.'),
        ], [
            'resumeChoice' => __('CV'),
            'newResume' => __('CV'),
            'coverLetter' => __('cover letter'),
            'screeningAnswers.*' => __('answer'),
        ]);

        $candidateProfile = auth()->user()->candidateProfile;

        if ($this->resumeChoice === 'new') {
            $resumeDocumentId = app(StoreCandidateDocument::class)($candidateProfile, DocumentType::Cv, $this->newResume)->id;
        } else {
            $resumeDocumentId = (int) $this->resumeChoice;
        }

        $application = Application::create([
            'job_posting_id' => $this->jobPosting->id,
            'candidate_profile_id' => $candidateProfile->id,
            'resume_document_id' => $resumeDocumentId,
            'cover_letter' => $this->coverLetter !== '' ? $this->coverLetter : null,
        ]);

        foreach ($this->screeningAnswers as $questionId => $answer) {
            if ($answer !== '') {
                $application->screeningAnswers()->create([
                    'screening_question_id' => $questionId,
                    'answer_text' => $answer,
                ]);
            }
        }

        // Announced only once everything the application is made of exists:
        // the screening answers are part of what the hiring team is about
        // to be told to go and read.
        ApplicationSubmitted::dispatch($application);

        RateLimiter::hit($limitKey, 86400);

        session()->flash('success', 'Application submitted — good luck!');

        $this->redirectRoute('jobs.show', $this->jobPosting, navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Apply: :job', ['job' => $this->jobPosting->title]));
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6 lg:pt-10">
    @php
        $company = $jobPosting->company;
        $pay = $jobPosting->payRange();
        $where = collect([$jobPosting->location_city, $jobPosting->workplace_type->label()])->filter()->implode(' · ');
    @endphp

    <x-breadcrumb :items="[
        ['label' => __('Jobs'), 'url' => route('jobs.index')],
        ['label' => $jobPosting->title, 'url' => route('jobs.show', $jobPosting)],
        ['label' => __('Apply')],
    ]" />

    <h1 class="mt-4 text-balance font-display text-heading text-ink sm:text-title">{{ __('Apply to :job', ['job' => $jobPosting->title]) }}</h1>
    <p class="mt-1 text-ink-muted">{{ $company->name }}</p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-10">
        <form wire:submit="submit" class="flex flex-col gap-8">
            <div>
                <flux:radio.group wire:model.live="resumeChoice" :label="__('CV')">
                    @foreach ($existingCvs as $cv)
                        <flux:radio
                            value="{{ $cv->id }}"
                            :label="$cv->original_filename"
                            :description="__('Uploaded :date', ['date' => \App\Support\LocalTime::of($cv->created_at)->format(\App\Support\DateFormat::DAY)])"
                        />
                    @endforeach
                    <flux:radio value="new" :label="__('Upload a new CV')" />
                </flux:radio.group>

                @if ($resumeChoice === 'new')
                    <div class="mt-4">
                        <flux:input
                            type="file"
                            wire:model="newResume"
                            :accept="\App\Support\DocumentUploads::accept(\App\Enums\DocumentType::Cv)"
                            :label="__('Your new CV')"
                            :description:trailing="\App\Support\DocumentUploads::hint(\App\Enums\DocumentType::Cv)"
                        />
                        <p wire:loading wire:target="newResume" class="mt-2 text-sm text-ink-muted" role="status">{{ __('Uploading your CV…') }}</p>
                    </div>
                @endif
            </div>

            {{-- "(optional)" in the label, not an asterisk on everything
                 else: GOV.UK's rule, since people read labels, not marks. --}}
            <x-rich-text-editor
                wire="coverLetter"
                :value="$coverLetter"
                :label="__('Cover letter (optional)')"
                :description="__('A few lines on why you fit this job.')"
                :headings="false"
            />

            @if ($jobPosting->screeningQuestions->isNotEmpty())
                <fieldset class="flex flex-col gap-6">
                    <legend class="font-display text-subheading text-ink">{{ __('Questions from :company', ['company' => $company->name]) }}</legend>
                    @foreach ($jobPosting->screeningQuestions as $question)
                        <flux:textarea wire:model="screeningAnswers.{{ $question->id }}" :label="$question->question_text" rows="3" />
                    @endforeach
                </fieldset>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-6">
                {{-- Held back while a CV is still uploading: sent then, the
                     form would go without the file it is waiting for. --}}
                <flux:button type="submit" variant="primary" class="btn-sunset" wire:loading.attr="disabled" wire:target="newResume">
                    {{ __('Submit application') }}
                </flux:button>
                <flux:button :href="route('jobs.show', $jobPosting)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>

        <aside class="flex flex-col gap-4 lg:sticky lg:top-20 lg:self-start" aria-label="{{ __('The job you are applying to') }}">
            <x-card subtle padding="sm">
                <div class="flex items-start gap-3">
                    <x-company-logo :company="$company" size="sm" />
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">{{ $jobPosting->title }}</p>
                        <p class="flex items-center gap-1 text-meta text-ink-muted">
                            {{ $company->name }}
                            @if ($company->verified_at)
                                <x-verified-badge />
                            @endif
                        </p>
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-meta">
                    <dt class="text-ink-muted">{{ __('Where') }}</dt>
                    <dd class="text-ink">{{ $where }}</dd>
                    <dt class="text-ink-muted">{{ __('Pay') }}</dt>
                    <dd class="text-ink">
                        @if ($jobPosting->salary_negotiable)
                            {{ __('Negotiable') }}
                        @elseif ($pay)
                            {{ $pay }} {{ $jobPosting->salary_period?->per() }}
                        @else
                            {{ __('Not stated') }}
                        @endif
                    </dd>
                    <dt class="text-ink-muted">{{ __('Closes') }}</dt>
                    <dd class="text-ink">{{ \App\Support\ClosingDate::day($jobPosting, $company)->format(\App\Support\DateFormat::DAY) }}</dd>
                </dl>
            </x-card>

            <x-card subtle padding="sm" class="text-meta">
                <h2 class="font-medium text-ink">{{ __('What :company sees', ['company' => $company->name]) }}</h2>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-ink-muted">
                    <li>{{ __('The CV, cover letter and answers you send now.') }}</li>
                    <li>{{ __('Your profile: photo, headline, bio, links, skills, experience and education, as they are when they read it.') }}</li>
                    <li>{{ __('How well your skills match the job.') }}</li>
                </ul>
                <p class="mt-2 text-ink-muted">{{ __('Your job preferences and the pay you want stay private.') }}</p>
                <a href="{{ route('candidate.profile.edit') }}" class="mt-3 inline-block font-medium text-sunset-small hover:underline" wire:navigate>{{ __('Check your profile') }}</a>
            </x-card>
        </aside>
    </div>
</div>
