<?php

use App\Actions\RespondToCompanyReview;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\ModerationEvent;
use App\Support\ReviewEligibility;
use App\Support\ReviewSummary;
use App\Support\ReviewTextFlags;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The company's own view of its hiring process reviews, and where its
 * owners and managers answer them.
 *
 * It shows exactly what the public sees -- published reviews, the month,
 * never the writer, the job or the application -- because a company that
 * could see more could work out who wrote what. Reviews still waiting for
 * staff, or kept off the page, are not shown here at all.
 */
new #[Layout('layouts::employer')] #[Title('Reviews')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 10;

    /**
     * The longest answer, as on the largest review sites.
     */
    public const MAX_RESPONSE = 5000;

    public Company $company;

    /**
     * "waiting" narrows the list to reviews still waiting on an answer.
     */
    #[Url]
    public string $show = 'all';

    /**
     * The review whose answer is open for writing.
     */
    public ?int $answering = null;

    public string $response = '';

    public ?int $withdrawing = null;

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->show = $this->validShow($this->show);
    }

    public function updatedShow(): void
    {
        $this->show = $this->validShow($this->show);
        $this->resetPage();
        $this->cancel();
    }

    #[Computed]
    public function reviews()
    {
        return $this->company->reviews()
            ->published()
            ->when($this->show === 'waiting', fn ($query) => $query->awaitingResponse())
            ->latest('published_at')
            ->latest('id')
            ->select([
                'id', 'company_id', 'overall_rating', 'communication_rating', 'job_as_described', 'title', 'body', 'published_at',
                'response_body', 'response_status', 'responded_at',
            ])
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function summary(): ReviewSummary
    {
        return ReviewSummary::of($this->company);
    }

    #[Computed]
    public function waitingCount(): int
    {
        return $this->company->reviews()->published()->awaitingResponse()->count();
    }

    #[Computed]
    public function canRespond(): bool
    {
        return auth()->user()->canManage($this->company);
    }

    /**
     * Why staff held back each answer on this page, as the company was
     * told it.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function rejectionReasons(): array
    {
        $rejected = $this->reviews->getCollection()
            ->filter(fn (CompanyReview $review) => $review->response_status === ModerationStatus::Rejected)
            ->modelKeys();

        if ($rejected === []) {
            return [];
        }

        return ModerationEvent::query()
            ->where('subject_type', (new CompanyReview)->getMorphClass())
            ->whereIn('subject_id', $rejected)
            ->where('action', ModerationAction::RejectReviewResponse->value)
            ->latest('created_at')
            ->latest('id')
            ->get(['subject_id', 'reason'])
            ->unique('subject_id')
            ->pluck('reason', 'subject_id')
            ->all();
    }

    public function startAnswer(int $reviewId): void
    {
        $review = $this->findReview($reviewId);
        $this->authorize('respond', $review);

        $this->answering = $review->id;
        $this->response = $review->response_body ?? '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->answering = null;
        $this->response = '';
        $this->resetValidation();
    }

    public function saveAnswer(RespondToCompanyReview $respond): void
    {
        $review = $this->findReview($this->answering);
        $this->authorize('respond', $review);

        $limitKey = SubmissionLimits::responseSaveKey($this->company);

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::RESPONSE_SAVES_PER_DAY)) {
            Flux::toast(
                variant: 'warning',
                duration: 10000,
                heading: __("You've reached today's limit for answers"),
                text: trans_choice('{1} Your company can save answers to reviews up to :limit times a day. You can save again in 1 hour.|[2,*] Your company can save answers to reviews up to :limit times a day. You can save again in :count hours.', SubmissionLimits::hoursUntilAvailable($limitKey), [
                    'limit' => SubmissionLimits::RESPONSE_SAVES_PER_DAY,
                ]),
            );

            return;
        }

        // Livewire's own requests skip the TrimStrings middleware.
        $this->response = trim($this->response);

        $this->validate([
            'response' => ['required', 'string', 'max:'.self::MAX_RESPONSE],
        ], attributes: ['response' => __('answer')]);

        $respond($review, auth()->user(), $this->response);

        RateLimiter::hit($limitKey, 86400);

        $this->cancel();
        unset($this->reviews, $this->waitingCount);

        Flux::toast(variant: 'success', text: __('Sent for a check. Your answer appears under the review once our team has read it.'));
    }

    public function confirmWithdraw(int $reviewId): void
    {
        $review = $this->findReview($reviewId);
        $this->authorize('respond', $review);

        $this->withdrawing = $review->id;
        Flux::modal('withdraw-response')->show();
    }

    public function withdraw(RespondToCompanyReview $respond): void
    {
        $review = $this->findReview($this->withdrawing);
        $this->authorize('respond', $review);

        $respond->withdraw($review);

        $this->withdrawing = null;
        $this->cancel();
        unset($this->reviews, $this->waitingCount);

        Flux::modal('withdraw-response')->close();
        Flux::toast(text: __('Your answer was removed.'));
    }

    /**
     * Only this company's published reviews can be answered from here,
     * whatever id a request carries.
     */
    private function findReview(?int $id): CompanyReview
    {
        return $this->company->reviews()->published()->whereKey($id)->firstOrFail();
    }

    private function validShow(string $show): string
    {
        return in_array($show, ['all', 'waiting'], true) ? $show : 'all';
    }
}; ?>

<x-page>
    <x-page-header :title="__('Reviews')">
        <x-slot:status>
            <x-visibility-badge public :tip="__('What applicants said about your hiring process, exactly as the public sees it. Nobody here can see who wrote a review.')" />
        </x-slot:status>
    </x-page-header>

    @if ($this->summary->count === 0)
        <x-empty-state icon="chat-bubble-left-right" :heading="__('No published reviews yet')">
            {{ __('Applicants can review your hiring process once they get a decision, reach an interview, or go :days days without an answer. Our team reads each one before it appears.', ['days' => ReviewEligibility::UNANSWERED_DAYS]) }}
        </x-empty-state>
    @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:text>
                @if ($this->summary->hasAverages())
                    {{ __(':count reviews · overall :overall · communication :communication · :yes of :count said the job was as described', [
                        'count' => $this->summary->count,
                        'overall' => number_format($this->summary->overall, 1),
                        'communication' => number_format($this->summary->communication, 1),
                        'yes' => $this->summary->asDescribed,
                    ]) }}
                @else
                    {{ trans_choice(':count published review. Averages appear on your page once there are :min.|:count published reviews. Averages appear on your page once there are :min.', $this->summary->count, [
                        'count' => $this->summary->count,
                        'min' => ReviewSummary::MIN_FOR_AVERAGES,
                    ]) }}
                @endif
            </flux:text>

            <flux:radio.group wire:model.live="show" variant="segmented" size="sm" aria-label="{{ __('Which reviews to show') }}">
                <flux:radio value="all" :label="__('All')" />
                <flux:radio value="waiting" :label="__('Waiting for an answer (:count)', ['count' => $this->waitingCount])" />
            </flux:radio.group>
        </div>

        @unless ($this->canRespond)
            <flux:callout icon="information-circle">
                <flux:callout.text>{{ __('Owners and managers can answer reviews on behalf of the company.') }}</flux:callout.text>
            </flux:callout>
        @endunless

        @if ($this->reviews->isEmpty())
            <x-empty-state icon="check-circle" :heading="__('Every review has an answer.')" />
        @endif

        <div class="flex flex-col gap-4">
            @foreach ($this->reviews as $review)
                <x-card as="article" wire:key="review-{{ $review->id }}" aria-labelledby="review-{{ $review->id }}-title">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <x-rating-stars :value="$review->overall_rating" />
                            <h2 id="review-{{ $review->id }}-title" class="mt-2 font-medium text-ink">{{ $review->title }}</h2>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ __('Verified applicant') }}
                                <span class="mx-1">·</span>
                                @php($publishedAt = \App\Support\LocalTime::of($review->published_at))
                                <time datetime="{{ $publishedAt->format('Y-m') }}">{{ $publishedAt->format(\App\Support\DateFormat::MONTH) }}</time>
                            </p>
                        </div>

                        <livewire:report-button :reportable="$review" :key="'report-review-'.$review->id" />
                    </div>

                    <p class="mt-3 whitespace-pre-line text-sm text-ink-soft">{{ $review->body }}</p>

                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div class="flex gap-1">
                            <dt class="text-ink-muted">{{ __('Communication') }}:</dt>
                            <dd class="font-medium text-ink">{{ __(':n out of 5', ['n' => $review->communication_rating]) }}</dd>
                        </div>
                        <div class="flex gap-1">
                            <dt class="text-ink-muted">{{ __('Job as described') }}:</dt>
                            <dd class="font-medium text-ink">{{ $review->job_as_described->label() }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 border-t border-line pt-4">
                        @if ($answering === $review->id)
                            <form wire:submit="saveAnswer" class="flex flex-col gap-3">
                                <flux:textarea
                                    wire:model="response"
                                    :label="__('Your public answer')"
                                    :description="__('Shown under the review as :company\'s answer once our team has read it. Answer the review, not the person: an answer that names or describes the writer, or threatens them, is not published.', ['company' => $company->name])"
                                    rows="6"
                                    maxlength="{{ $this::MAX_RESPONSE }}"
                                />
                                <div class="flex justify-end gap-2">
                                    <flux:button type="button" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                                    <flux:button type="submit" variant="primary">{{ __('Send for a check') }}</flux:button>
                                </div>
                            </form>
                        @elseif ($review->response_status === null)
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <flux:text>{{ __('No answer yet.') }}</flux:text>
                                @if ($this->canRespond)
                                    <flux:button size="sm" wire:click="startAnswer({{ $review->id }})">{{ __('Answer publicly') }}</flux:button>
                                @endif
                            </div>
                        @else
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-ink">{{ __('Your answer') }}</span>
                                    @switch($review->response_status)
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

                                @if ($this->canRespond)
                                    <div class="flex gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="startAnswer({{ $review->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="confirmWithdraw({{ $review->id }})">{{ __('Remove') }}</flux:button>
                                    </div>
                                @endif
                            </div>

                            <p class="mt-2 whitespace-pre-line text-sm text-ink-soft">{{ $review->response_body }}</p>

                            @if ($review->response_status === ModerationStatus::Rejected)
                                <flux:callout variant="danger" icon="x-circle" class="mt-3">
                                    <flux:callout.heading>{{ __('Our team did not publish this answer') }}</flux:callout.heading>
                                    @if (isset($this->rejectionReasons[$review->id]))
                                        <flux:callout.text class="whitespace-pre-line">{{ $this->rejectionReasons[$review->id] }}</flux:callout.text>
                                    @endif
                                </flux:callout>
                            @elseif ($review->response_status === ModerationStatus::Pending)
                                @php($flags = ReviewTextFlags::in($review->response_body))
                                @if ($flags !== [])
                                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-3">
                                        <flux:callout.text>
                                            {{ __('Your answer contains :what. Personal contact details are not published; a general address for the company is fine.', [
                                                'what' => collect($flags)->map(fn (string $flag) => ReviewTextFlags::label($flag))->join(', ', ' and '),
                                            ]) }}
                                        </flux:callout.text>
                                    </flux:callout>
                                @endif
                            @elseif ($review->responseAnswersEarlierVersion())
                                <flux:text class="mt-2 text-xs">
                                    {{ __('The writer has changed the review since you answered, and readers are told so. Edit your answer if it no longer fits.') }}
                                </flux:text>
                            @endif
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>

        {{ $this->reviews->links() }}
    @endif

    <flux:modal name="withdraw-response" class="max-w-md">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Remove your answer?') }}</flux:heading>
                <flux:text>{{ __('It comes off the review straight away. The review itself stays.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Keep it') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="withdraw">{{ __('Remove answer') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-page>
