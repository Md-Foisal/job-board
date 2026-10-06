<?php

use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Jobs\ReviewJobPostWithAi;
use App\Models\JobPosting;
use App\Support\AiQuota;
use App\Support\JobPostReview;
use App\Support\JobPostReviewInput;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The AI's review of one job posting, on the analytics page.
 *
 * Every member of the team can read a review once it exists; only those
 * who can edit the posting can ask for one, since the allowance is the
 * company's and the advice is about changing the posting. It runs in the
 * background while the page polls, and only when asked: never on a page
 * view.
 */
new class extends Component
{
    public JobPosting $jobPosting;

    /**
     * Where the review is: null (not asked), running, done, failed, or
     * unavailable (the allowance ran out before it started).
     */
    #[Locked]
    public ?string $aiStatus = null;

    /**
     * The cache key of the review being waited for or shown, fixed when it
     * was asked for so the poll reads the same one.
     */
    #[Locked]
    public ?string $aiKey = null;

    /**
     * @var array{issues: array<int, array{area: string, problem: string, suggestion: string}>, suggested_title: ?string}|array{}
     */
    #[Locked]
    public array $review = [];

    #[Locked]
    public ?string $reviewedAt = null;

    /**
     * A review already written for the posting as it is now is shown
     * again rather than asked for, and paid for, twice.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->worksAt($this->jobPosting->company), 404);

        if (! AiQuota::enabled()) {
            return;
        }

        $key = JobPostReviewInput::cacheKey($this->jobPosting);
        $result = Cache::get($key);

        if (($result['status'] ?? null) === 'done') {
            $this->show($key, $result);
        } elseif (Cache::has(ReviewJobPostWithAi::runningKey($key))) {
            [$this->aiKey, $this->aiStatus] = [$key, 'running'];
        }
    }

    #[Computed]
    public function aiAvailability(): AiAvailability
    {
        return AiQuota::availability(AiFeature::JobPostReview, auth()->user(), $this->jobPosting->company);
    }

    #[Computed]
    public function canRequest(): bool
    {
        return auth()->user()->can('update', $this->jobPosting);
    }

    public function requestReview(): void
    {
        $this->authorize('update', $this->jobPosting);

        if ($this->aiStatus === 'running') {
            return;
        }

        if ($this->aiAvailability !== AiAvailability::Available) {
            $this->aiStatus = 'unavailable';

            return;
        }

        $key = JobPostReviewInput::cacheKey($this->jobPosting);
        $result = Cache::get($key);

        if (($result['status'] ?? null) === 'done') {
            $this->show($key, $result);

            return;
        }

        $attempts = ReviewJobPostWithAi::attemptsKey($this->jobPosting->company);

        if (RateLimiter::tooManyAttempts($attempts, ReviewJobPostWithAi::DAILY_ATTEMPTS)) {
            Flux::toast(variant: 'warning', text: __('Your team can ask for an AI review :count times a day. Try again tomorrow.', [
                'count' => ReviewJobPostWithAi::DAILY_ATTEMPTS,
            ]));

            return;
        }

        [$this->aiKey, $this->aiStatus] = [$key, 'running'];

        // A second click, or a teammate asking at the same moment, finds
        // the mark already set and waits for the run already under way.
        if (! Cache::add(ReviewJobPostWithAi::runningKey($key), true, ReviewJobPostWithAi::RUNNING_SECONDS)) {
            return;
        }

        RateLimiter::hit($attempts, 24 * 60 * 60);
        Cache::forget($key);

        ReviewJobPostWithAi::dispatch($this->jobPosting->id, auth()->id(), $key);

        $this->checkAi();
    }

    /**
     * Polled while the review runs. When the running mark has expired
     * without a result, the job never finished, and the page says so
     * instead of waiting for ever.
     */
    public function checkAi(): void
    {
        if ($this->aiStatus !== 'running' || $this->aiKey === null) {
            return;
        }

        $result = Cache::get($this->aiKey);

        if ($result === null) {
            if (! Cache::has(ReviewJobPostWithAi::runningKey($this->aiKey))) {
                $this->aiStatus = 'failed';
            }

            return;
        }

        if ($result['status'] !== 'done') {
            $this->aiStatus = $result['status'] === 'unavailable' ? 'unavailable' : 'failed';

            return;
        }

        $this->show($this->aiKey, $result);
    }

    public function shownReview(): ?JobPostReview
    {
        return $this->aiStatus === 'done' ? JobPostReview::fromArray($this->review) : null;
    }

    private function show(string $key, array $result): void
    {
        [$this->aiKey, $this->review, $this->reviewedAt, $this->aiStatus] = [$key, $result['review'], $result['reviewed_at'], 'done'];
    }
}; ?>

<div>
    @php($availability = $this->aiAvailability)

    {{-- Plans without the review see nothing here, unless one was written
         before the plan changed. --}}
    @if ($aiStatus !== null || in_array($availability, [\App\Enums\AiAvailability::Available, \App\Enums\AiAvailability::LimitReached], true))
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="ai-review-heading">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="ai-review-heading" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('AI review of this posting') }}</h2>
                @if ($aiStatus === 'done')
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('AI-generated — may be wrong') }}</span>
                @endif
            </div>

            @if ($aiStatus === 'running')
                <div wire:poll.2s="checkAi" role="status" class="mt-3 flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-100">
                    <flux:icon.loading variant="mini" />
                    <div>
                        <p class="font-medium">{{ __('Reading the posting…') }}</p>
                        <p>{{ __('This usually takes a few seconds.') }}</p>
                    </div>
                </div>
            @elseif ($shown = $this->shownReview())
                @if ($shown->isEmpty())
                    <flux:text class="mt-2">{{ __('The AI found nothing it would change in this posting.') }}</flux:text>
                @else
                    @if ($shown->suggestedTitle !== null)
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm" x-data="copyText">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('A clearer title:') }}</span>
                            <span x-ref="text" class="font-medium text-zinc-900 dark:text-zinc-100">{{ $shown->suggestedTitle }}</span>
                            <flux:button size="xs" variant="ghost" icon="clipboard" x-on:click="copy($refs.text.innerText)">
                                <span x-show="copyState !== 'copied'">{{ __('Copy') }}</span>
                                <span x-show="copyState === 'copied'" x-cloak>{{ __('Copied') }}</span>
                            </flux:button>
                            <span role="status" class="text-red-600 dark:text-red-400" x-bind:class="copyState === 'failed' ? '' : 'sr-only'" x-text="({ copied: @js(__('Copied')), failed: @js(__("Couldn't copy — select the text and copy it yourself.")) })[copyState] ?? ''"></span>
                        </div>
                    @endif

                    @if ($shown->issues !== [])
                        <ol class="mt-3 flex flex-col divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($shown->issues as $issue)
                                <li class="py-3 first:pt-0" x-data="copyText">
                                    <span class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __(\App\Enums\JobPostReviewArea::from($issue['area'])->label()) }}</span>
                                    <p class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $issue['problem'] }}</p>
                                    <p x-ref="text" class="mt-1 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $issue['suggestion'] }}</p>
                                    <flux:button class="mt-2" size="xs" variant="ghost" icon="clipboard" x-on:click="copy($refs.text.innerText)">
                                        <span x-show="copyState !== 'copied'">{{ __('Copy suggestion') }}</span>
                                        <span x-show="copyState === 'copied'" x-cloak>{{ __('Copied') }}</span>
                                    </flux:button>
                                    <p role="status" class="mt-1 text-red-600 dark:text-red-400" x-bind:class="copyState === 'failed' ? '' : 'sr-only'" x-text="({ copied: @js(__('Copied')), failed: @js(__("Couldn't copy — select the text and copy it yourself.")) })[copyState] ?? ''"></p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                @endif

                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Written :when, from the posting and its last :days days of numbers. Nothing changes until you edit the posting, and an edited posting is checked again before it goes live.', [
                        'when' => \Carbon\CarbonImmutable::parse($reviewedAt)->diffForHumans(),
                        'days' => \App\Support\JobPostReviewInput::RANGE_DAYS,
                    ]) }}
                </p>

                @if ($this->canRequest)
                    <flux:button class="mt-3" size="sm" :href="route('employer.jobs.edit', ['company' => $jobPosting->company, 'jobPosting' => $jobPosting])" wire:navigate>
                        {{ __('Edit the posting') }}
                    </flux:button>
                @endif
            @elseif ($aiStatus === 'unavailable' || $availability === \App\Enums\AiAvailability::LimitReached)
                <flux:text class="mt-2">
                    {{ __("Your company has used this month's AI reviews. They reset on :date.", ['date' => \App\Support\LocalTime::of(now()->startOfMonth()->addMonth())->format(\App\Support\DateFormat::MOMENT)]) }}
                </flux:text>
            @elseif (! $this->canRequest)
                <flux:text class="mt-2">{{ __('Owners and managers can ask the AI to review this posting.') }}</flux:text>
            @else
                @if ($aiStatus === 'failed')
                    <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __("The AI couldn't review this posting right now.") }}</p>
                    <flux:text class="mt-1">{{ __('You can try again; the numbers above do not depend on it.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('The AI can read this posting and its numbers and suggest what to change: the title, pay, requirements and description.') }}</flux:text>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <flux:button wire:click="requestReview" icon="sparkles" size="sm">
                        {{ $aiStatus === 'failed' ? __('Try again') : __('Review with AI') }}
                    </flux:button>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __("The posting and its totals (nothing about any applicant) are sent to Anthropic. Anthropic doesn't train on it and, by default, deletes it within 30 days.") }}
                    </span>
                </div>
            @endif
        </section>
    @endif
</div>
