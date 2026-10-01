<?php

use App\Actions\SubmitCompanyReview;
use App\Enums\JobAsDescribed;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\CompanyReview;
use App\Support\ReviewTextFlags;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The candidate's review of a company's hiring process, on their own
 * application page. A candidate has one review per company, so the same
 * review shows on every application they made there, and is written or
 * revised from any of them once one qualifies.
 */
new class extends Component
{
    public Application $application;

    public bool $showForm = false;

    public ?string $overallRating = null;

    public ?string $communicationRating = null;

    public string $jobAsDescribed = '';

    public string $title = '';

    public string $body = '';

    /**
     * Only the candidate's own application. The page checks this too; the
     * component checks again because it answers requests of its own.
     */
    public function mount(): void
    {
        $this->authorize('view', $this->application);
    }

    /**
     * The candidate's existing review of this company, if any.
     */
    #[Computed]
    public function review(): ?CompanyReview
    {
        return CompanyReview::query()
            ->where('company_id', $this->application->jobPosting->company_id)
            ->where('candidate_profile_id', $this->application->candidate_profile_id)
            ->with('application.jobPosting:id,title')
            ->first();
    }

    /**
     * Why staff held the review back, as it was written to the writer.
     */
    #[Computed]
    public function rejectionReason(): ?string
    {
        return $this->review?->moderationEvents()
            ->where('action', ModerationAction::RejectCompanyReview->value)
            ->latest('created_at')
            ->latest('id')
            ->value('reason');
    }

    #[Computed]
    public function canWrite(): bool
    {
        return $this->review === null
            && auth()->user()->can('create', [CompanyReview::class, $this->application]);
    }

    public function open(): void
    {
        $review = $this->review;

        if ($review === null) {
            $this->authorize('create', [CompanyReview::class, $this->application]);
            $this->resetForm();
        } else {
            $this->authorize('update', $review);
            $this->overallRating = (string) $review->overall_rating;
            $this->communicationRating = (string) $review->communication_rating;
            $this->jobAsDescribed = $review->job_as_described->value;
            $this->title = $review->title;
            $this->body = $review->body;
            $this->resetValidation();
        }

        $this->showForm = true;
    }

    public function save(SubmitCompanyReview $submit): void
    {
        $review = $this->review;

        $review === null
            ? $this->authorize('create', [CompanyReview::class, $this->application])
            : $this->authorize('update', $review);

        $limitKey = SubmissionLimits::reviewSaveKey(auth()->user());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::REVIEW_SAVES_PER_DAY)) {
            Flux::toast(
                variant: 'warning',
                duration: 10000,
                heading: __("You've reached today's limit for reviews"),
                text: trans_choice('{1} You can save a review up to :limit times a day. You can save again in 1 hour.|[2,*] You can save a review up to :limit times a day. You can save again in :count hours.', SubmissionLimits::hoursUntilAvailable($limitKey), [
                    'limit' => SubmissionLimits::REVIEW_SAVES_PER_DAY,
                ]),
            );

            return;
        }

        // Livewire's own requests skip the TrimStrings middleware, so the
        // lengths below would otherwise count the spaces around the text.
        $this->title = Str::squish($this->title);
        $this->body = trim($this->body);

        $validated = $this->validate([
            'overallRating' => ['required', 'integer', 'between:1,5'],
            'communicationRating' => ['required', 'integer', 'between:1,5'],
            'jobAsDescribed' => ['required', Rule::enum(JobAsDescribed::class)],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:50', 'max:2000'],
        ], [
            'overallRating.required' => __('Choose how the process went, from 1 to 5.'),
            'communicationRating.required' => __('Choose how well they kept you informed, from 1 to 5.'),
            'jobAsDescribed.required' => __('Choose whether the job was what the posting said.'),
            'title.required' => __('Give your review a headline.'),
            'title.max' => __('Keep the headline to 100 characters.'),
            'body.required' => __('Say what happened during the process.'),
            'body.min' => __('Write at least 50 characters about what happened.'),
            'body.max' => __('Keep it to 2,000 characters.'),
        ], [
            'overallRating' => __('overall rating'),
            'communicationRating' => __('communication rating'),
            'jobAsDescribed' => __('answer'),
            'title' => __('headline'),
            'body' => __('what happened'),
        ]);

        $submit($this->application, [
            'overall_rating' => (int) $validated['overallRating'],
            'communication_rating' => (int) $validated['communicationRating'],
            'job_as_described' => $validated['jobAsDescribed'],
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        RateLimiter::hit($limitKey, 86400);

        unset($this->review, $this->canWrite);
        $this->showForm = false;

        Flux::toast(variant: 'success', text: $review === null
            ? __('Thanks — your review will appear once it has been checked.')
            : __('Saved. Your review goes back for a check before it appears again.'));
    }

    public function delete(): void
    {
        $review = $this->review;

        if ($review === null) {
            return;
        }

        $this->authorize('delete', $review);
        $review->delete();

        unset($this->review, $this->canWrite);
        Flux::modal('delete-company-review')->close();
        Flux::toast(text: __('Your review was deleted.'));
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->overallRating = null;
        $this->communicationRating = null;
        $this->jobAsDescribed = '';
        $this->title = '';
        $this->body = '';
        $this->resetValidation();
    }
}; ?>

<div>
    @if ($this->review !== null || $this->canWrite)
        <flux:separator variant="subtle" class="my-8" />

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            @if ($this->review === null)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Review this hiring process') }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-500">
                            {{ __('Help the next applicant: how did :company handle your application?', ['company' => $application->jobPosting->company->name]) }}
                        </p>
                    </div>

                    <flux:button wire:click="open" variant="primary" size="sm">{{ __('Write a review') }}</flux:button>
                </div>
            @else
                @php($review = $this->review)

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Your review of :company', ['company' => $application->jobPosting->company->name]) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-500">
                            {{ __('About your application for :job, :month.', [
                                'job' => $review->application->jobPosting->title,
                                'month' => \App\Support\LocalTime::of($review->application->created_at)->format('F Y'),
                            ]) }}
                        </p>
                    </div>

                    @switch($review->moderation_status)
                        @case(ModerationStatus::Approved)
                            <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                            @break
                        @case(ModerationStatus::Rejected)
                            <flux:badge color="red" size="sm">{{ __('Not published') }}</flux:badge>
                            @break
                        @default
                            <flux:badge color="blue" size="sm">{{ __('Waiting for a check') }}</flux:badge>
                    @endswitch
                </div>

                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Overall') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ __(':n out of 5', ['n' => $review->overall_rating]) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Communication') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ __(':n out of 5', ['n' => $review->communication_rating]) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Job as described') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $review->job_as_described->label() }}</dd>
                    </div>
                </dl>

                <p class="mt-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $review->title }}</p>
                <p class="mt-1 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $review->body }}</p>

                @if ($review->moderation_status === ModerationStatus::Pending)
                    <flux:text class="mt-4">
                        {{ __('Someone on our team reads every review before it appears. Your name, the job and the outcome are never shown with it — only "Verified applicant" and the month.') }}
                    </flux:text>
                @elseif ($review->moderation_status === ModerationStatus::Rejected)
                    <flux:callout variant="danger" icon="x-circle" class="mt-4">
                        <flux:callout.heading>{{ __('Our team did not publish this review') }}</flux:callout.heading>
                        @if ($this->rejectionReason !== null)
                            <flux:callout.text class="whitespace-pre-line">{{ $this->rejectionReason }}</flux:callout.text>
                        @endif
                        <flux:callout.text>{{ __('You can edit it and send it for another check.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                @php($flags = ReviewTextFlags::in($review->title, $review->body))
                @if ($flags !== [] && $review->moderation_status !== ModerationStatus::Approved)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                        <flux:callout.text>
                            {{ __('Your review seems to contain :things. Reviews that could identify someone or point elsewhere are not published, so take it out now rather than wait to be asked.', [
                                'things' => collect($flags)->map(fn ($flag) => ReviewTextFlags::label($flag))->join(', ', __(' and ')),
                            ]) }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <x-review-response :review="$review" :company="$application->jobPosting->company" />

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @can('update', $review)
                        <flux:button wire:click="open" size="sm" icon="pencil">{{ __('Edit') }}</flux:button>
                    @else
                        <flux:text size="sm">{{ __('You have since joined this company, so you can no longer edit this review. You can still delete it.') }}</flux:text>
                    @endcan

                    @can('delete', $review)
                        <flux:modal.trigger name="delete-company-review">
                            <flux:button size="sm" variant="ghost">{{ __('Delete') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan
                </div>

                <flux:modal name="delete-company-review" class="max-w-md">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Delete your review?') }}</flux:heading>
                            <flux:subheading>{{ __('It is deleted for good, along with any answer the company wrote, and comes off the company page if it is there. This cannot be undone.') }}</flux:subheading>
                        </div>

                        <div class="flex gap-2">
                            <flux:spacer />
                            <flux:modal.close>
                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button wire:click="delete" variant="danger">{{ __('Delete') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @endif
        </div>

        <flux:modal wire:model="showForm" class="max-w-xl" @close="closeForm">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Review :company\'s hiring process', ['company' => $application->jobPosting->company->name]) }}</flux:heading>
                    <flux:subheading>
                        {{ __('About the process, not about any one person. Shown as "Verified applicant" with the month — never your name, the job or the outcome.') }}
                    </flux:subheading>
                </div>

                <flux:radio.group wire:model="overallRating" variant="segmented" :label="__('Overall, how was the process?')" :description="__('1 is very poor, 5 is excellent.')">
                    @foreach (range(1, 5) as $n)
                        <flux:radio value="{{ $n }}" :label="(string) $n" />
                    @endforeach
                </flux:radio.group>

                <flux:radio.group wire:model="communicationRating" variant="segmented" :label="__('Did they keep you informed?')" :description="__('1 is never, 5 is always.')">
                    @foreach (range(1, 5) as $n)
                        <flux:radio value="{{ $n }}" :label="(string) $n" />
                    @endforeach
                </flux:radio.group>

                <flux:radio.group wire:model="jobAsDescribed" variant="segmented" :label="__('Was the job what the posting said?')">
                    @foreach (JobAsDescribed::cases() as $answer)
                        <flux:radio value="{{ $answer->value }}" :label="$answer->label()" />
                    @endforeach
                </flux:radio.group>

                <flux:input wire:model="title" :label="__('Headline')" maxlength="100" />

                <flux:textarea
                    wire:model="body"
                    :label="__('What happened')"
                    :description="__('Between 50 and 2,000 characters. No names, contact details or links.')"
                    rows="6"
                    maxlength="2000"
                />

                @if ($this->review?->hasPublishedResponse())
                    <flux:text>
                        {{ __('The company has answered your review. If you change it, the answer stays under it, marked as written to an earlier version, until the company updates it.') }}
                    </flux:text>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeForm">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Send for review') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
