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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::guest')] #[Title('Apply')] class extends Component {
    use WithFileUploads;

    public JobPosting $jobPosting;

    public Collection $existingCvs;

    public string $resumeChoice = 'new';

    public $newResume = null;

    public string $coverLetter = '';

    public array $screeningAnswers = [];

    public function mount(JobPosting $jobPosting): void
    {
        $this->authorize('create', [Application::class, $jobPosting]);

        $jobPosting->load(['company:id,name,slug', 'screeningQuestions']);
        $this->jobPosting = $jobPosting;

        $this->existingCvs = auth()->user()->candidateProfile->documents()
            ->where('document_type', DocumentType::Cv)
            ->latest()
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
                text: __('We cap applications at :limit a day so each one gets proper attention. You can apply again in :hours hours.', [
                    'limit' => SubmissionLimits::APPLICATIONS_PER_DAY,
                    'hours' => SubmissionLimits::hoursUntilAvailable($limitKey),
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

        $this->validate($rules);

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
}; ?>

<div class="mx-auto max-w-2xl px-6 py-12">
    <nav class="text-sm text-zinc-500 dark:text-zinc-500">
        <a href="{{ route('jobs.show', $jobPosting) }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>{{ $jobPosting->title }}</a>
        <span class="mx-1">/</span>
        <span class="text-zinc-700 dark:text-zinc-300">Apply</span>
    </nav>

    <h1 class="mt-2 font-display text-2xl font-bold text-zinc-900 dark:text-zinc-50">Apply to {{ $jobPosting->title }}</h1>
    <p class="mt-1 text-zinc-500 dark:text-zinc-500">{{ $jobPosting->company->name }}</p>

    <form wire:submit="submit" class="mt-8 space-y-8">
        <div>
            <flux:radio.group wire:model="resumeChoice" label="Resume">
                @foreach ($existingCvs as $cv)
                    <flux:radio value="{{ $cv->id }}" label="{{ $cv->original_filename }}" />
                @endforeach
                <flux:radio value="new" label="Upload a new resume" />
            </flux:radio.group>

            @if ($resumeChoice === 'new')
                <div class="mt-3">
                    <flux:input type="file" wire:model="newResume" :accept="\App\Support\DocumentUploads::accept(\App\Enums\DocumentType::Cv)" />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ \App\Support\DocumentUploads::hint(\App\Enums\DocumentType::Cv) }}</p>
                </div>
            @endif
        </div>

        <x-rich-text-editor wire="coverLetter" :value="$coverLetter" :label="__('Cover letter')" :description="__('Why you are a good fit (optional)')" :headings="false" />

        @if ($jobPosting->screeningQuestions->isNotEmpty())
            <div class="space-y-6">
                @foreach ($jobPosting->screeningQuestions as $question)
                    <flux:textarea wire:model="screeningAnswers.{{ $question->id }}" label="{{ $question->question_text }}" rows="3" />
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Submit application</flux:button>
            <flux:button href="{{ route('jobs.show', $jobPosting) }}" variant="ghost" wire:navigate>Cancel</flux:button>
        </div>
    </form>
</div>
