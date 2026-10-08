<?php

use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\MatchCheck;
use App\Enums\MatchCheckResult;
use App\Enums\SalaryPeriod;
use App\Jobs\ExplainMatchWithAi;
use App\Models\CandidatePreference;
use App\Models\JobPosting;
use App\Services\MatchScoreCalculator;
use App\Support\AiQuota;
use App\Support\MatchBreakdown;
use App\Support\MatchExplanation;
use App\Support\MatchExplanationInput;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The candidate's own view of how they fit one job, beside the Apply
 * button. It loads in its own request after the page, so the job page
 * itself stays the same for everyone and one candidate's details never
 * sit in a page another person could be served.
 *
 * On a paid plan the candidate can also ask the AI to explain the match
 * in words. That runs in the background while the page polls, and only
 * when asked: never on a page view.
 */
new class extends Component
{
    public JobPosting $jobPosting;

    /**
     * Where the AI explanation is: null (not asked), running, done,
     * failed, or unavailable (the allowance ran out before it started).
     */
    #[Locked]
    public ?string $aiStatus = null;

    /**
     * The cache key of the explanation being waited for or shown, fixed
     * when it was asked for so the poll reads the same one.
     */
    #[Locked]
    public ?string $aiKey = null;

    /**
     * @var array{summary: string, strengths: array<int, string>, gaps: array<int, string>, tips: array<int, string>}|array{}
     */
    #[Locked]
    public array $explanation = [];

    /**
     * An explanation already written for this job and profile as they are
     * now is shown again rather than asked for, and paid for, twice.
     */
    public function mount(): void
    {
        if ($this->breakdown === null || ! AiQuota::enabled()) {
            return;
        }

        $key = $this->currentAiKey();
        $result = Cache::get($key);

        if (($result['status'] ?? null) === 'done') {
            [$this->aiKey, $this->explanation, $this->aiStatus] = [$key, $result['explanation'], 'done'];
        } elseif (Cache::has(ExplainMatchWithAi::runningKey($key))) {
            [$this->aiKey, $this->aiStatus] = [$key, 'running'];
        }
    }

    /**
     * Nothing for anyone but a candidate on a job the public can see: an
     * employer previewing a draft, staff, or a guest get an empty block
     * even if they reach the component directly.
     */
    #[Computed]
    public function breakdown(): ?MatchBreakdown
    {
        $profile = auth()->user()?->candidateProfile;

        if ($profile === null || ! $this->jobPosting->isPubliclyVisible()) {
            return null;
        }

        return app(MatchScoreCalculator::class)->breakdown($this->jobPosting, $profile);
    }

    #[Computed]
    public function aiAvailability(): AiAvailability
    {
        return AiQuota::availability(AiFeature::MatchExplanation, auth()->user());
    }

    /**
     * With no skills and no work history there is nothing for the AI to
     * explain, and a run would only spend the allowance on generalities.
     */
    #[Computed]
    public function aiHasMaterial(): bool
    {
        return $this->breakdown->experienceMonths !== null
            || auth()->user()->candidateProfile->skills()->exists();
    }

    public function explain(): void
    {
        if ($this->breakdown === null || $this->aiStatus === 'running' || ! $this->aiHasMaterial) {
            return;
        }

        if ($this->aiAvailability !== AiAvailability::Available) {
            $this->aiStatus = 'unavailable';

            return;
        }

        $key = $this->currentAiKey();
        $result = Cache::get($key);

        if (($result['status'] ?? null) === 'done') {
            [$this->aiKey, $this->explanation, $this->aiStatus] = [$key, $result['explanation'], 'done'];

            return;
        }

        $attempts = ExplainMatchWithAi::attemptsKey(auth()->user());

        if (RateLimiter::tooManyAttempts($attempts, ExplainMatchWithAi::DAILY_ATTEMPTS)) {
            Flux::toast(variant: 'warning', text: __('You can ask the AI to explain a match :count times a day. Try again tomorrow.', [
                'count' => ExplainMatchWithAi::DAILY_ATTEMPTS,
            ]));

            return;
        }

        [$this->aiKey, $this->aiStatus] = [$key, 'running'];

        // A second click, or the same job open in another tab, finds the
        // mark already set and waits for the run already under way.
        if (! Cache::add(ExplainMatchWithAi::runningKey($key), true, ExplainMatchWithAi::RUNNING_SECONDS)) {
            return;
        }

        RateLimiter::hit($attempts, 24 * 60 * 60);
        Cache::forget($key);

        ExplainMatchWithAi::dispatch($this->jobPosting->id, auth()->id(), $key);

        $this->checkAi();
    }

    /**
     * Polled while the explanation runs. When the running mark has expired
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
            if (! Cache::has(ExplainMatchWithAi::runningKey($this->aiKey))) {
                $this->aiStatus = 'failed';
            }

            return;
        }

        if ($result['status'] !== 'done') {
            $this->aiStatus = $result['status'] === 'unavailable' ? 'unavailable' : 'failed';

            return;
        }

        [$this->explanation, $this->aiStatus] = [$result['explanation'], 'done'];
    }

    public function shownExplanation(): ?MatchExplanation
    {
        return $this->explanation === [] ? null : MatchExplanation::fromArray($this->explanation);
    }

    private function currentAiKey(): string
    {
        $profile = auth()->user()->candidateProfile;

        return MatchExplanationInput::cacheKey(
            $profile,
            $this->jobPosting,
            MatchExplanationInput::for($this->jobPosting, $profile, $this->breakdown),
        );
    }

    #[Computed]
    public function preference(): ?CandidatePreference
    {
        return auth()->user()?->candidateProfile?->preference;
    }

    /**
     * @return array<int, array{check: MatchCheck, result: MatchCheckResult, job: string, you: string}>
     */
    public function rows(): array
    {
        $breakdown = $this->breakdown;
        $preference = $this->preference;

        return [
            [
                'check' => MatchCheck::Salary,
                'result' => $breakdown->check(MatchCheck::Salary),
                'job' => $this->jobSalary(),
                'you' => $preference?->desired_salary_min === null
                    ? __('Not set')
                    : __('From :amount a month', ['amount' => Money::format($preference->desired_salary_currency, $preference->desired_salary_min)]),
            ],
            [
                'check' => MatchCheck::Workplace,
                'result' => $breakdown->check(MatchCheck::Workplace),
                'job' => $this->jobPosting->workplace_type->label(),
                'you' => $preference?->preferred_workplace_type?->label() ?? __('No preference'),
            ],
            [
                'check' => MatchCheck::Employment,
                'result' => $breakdown->check(MatchCheck::Employment),
                'job' => $this->jobPosting->employment_type->label(),
                'you' => $preference?->preferred_employment_type?->label() ?? __('No preference'),
            ],
            [
                'check' => MatchCheck::Experience,
                'result' => $breakdown->check(MatchCheck::Experience),
                'job' => match ($this->jobPosting->min_experience_years) {
                    null => __('Not stated'),
                    0 => __('No minimum'),
                    default => trans_choice('{1} :count+ year|[2,*] :count+ years', $this->jobPosting->min_experience_years),
                },
                'you' => $breakdown->experienceMonths === null
                    ? __('No work history listed')
                    : $this->duration($breakdown->experienceMonths),
            ],
        ];
    }

    /**
     * The posting's pay as the check compares it: a month's worth. A
     * posting paid by the hour, week or year is converted, so the figure
     * is marked as approximate.
     */
    private function jobSalary(): string
    {
        $job = $this->jobPosting;

        if ($job->salary_negotiable) {
            return __('Negotiable');
        }

        $min = $job->salary_min_monthly;
        $max = $job->salary_max_monthly;

        if ($min === null && $max === null) {
            return __('Not stated');
        }

        $amount = match (true) {
            $min !== null && $max !== null => Money::format($job->salary_currency, $min).'–'.Money::format($job->salary_currency, $max),
            $min !== null => __('from :amount', ['amount' => Money::format($job->salary_currency, $min)]),
            default => __('up to :amount', ['amount' => Money::format($job->salary_currency, $max)]),
        };

        return $job->salary_period === SalaryPeriod::Monthly
            ? __(':amount a month', ['amount' => $amount])
            : __('About :amount a month', ['amount' => $amount]);
    }

    private function duration(int $months): string
    {
        $years = intdiv($months, 12);
        $rest = $months % 12;

        return collect([
            $years > 0 ? trans_choice('{1} :count year|[2,*] :count years', $years) : null,
            $rest > 0 || $years === 0 ? trans_choice('{0} :count months|{1} :count month|[2,*] :count months', $rest) : null,
        ])->filter()->implode(' ');
    }
};
?>

@placeholder
    <x-card as="section" padding="sm" class="mt-6" aria-busy="true">
        <span class="sr-only">{{ __('Loading how you match this job') }}</span>
        <div class="animate-pulse space-y-3" aria-hidden="true">
            <div class="h-5 w-40 rounded bg-line"></div>
            <div class="h-4 w-3/4 rounded bg-line/60"></div>
            <div class="h-4 w-2/3 rounded bg-line/60"></div>
            <div class="h-4 w-1/2 rounded bg-line/60"></div>
        </div>
    </x-card>
@endplaceholder

<div>
    @if ($this->breakdown)
        @php($breakdown = $this->breakdown)

        <x-card as="section" padding="sm" class="mt-6" aria-labelledby="match-breakdown-heading">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="match-breakdown-heading" class="font-display text-base font-semibold text-ink">
                    {{ __('How you match') }}
                </h2>
                <x-match-score :score="$breakdown->score" size="md" />
                <span class="text-xs text-ink-muted">{{ __('Only you can see this.') }}</span>
            </div>

            @if ($breakdown->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">
                    {{ __('Your profile is empty, so there is nothing to compare yet. Add your skills, work history and job preferences, or fill them in from your CV.') }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" variant="primary" href="{{ route('candidate.documents.index') }}" wire:navigate>{{ __('Fill in from my CV') }}</flux:button>
                    <flux:button size="sm" href="{{ route('candidate.skills.edit') }}" wire:navigate>{{ __('Add skills') }}</flux:button>
                </div>
            @else
                @php($skillGroups = [
                    ['label' => __('Required skills'), 'matched' => $breakdown->matchedRequired, 'missing' => $breakdown->missingRequired],
                    ['label' => __('Nice to have'), 'matched' => $breakdown->matchedNiceToHave, 'missing' => $breakdown->missingNiceToHave],
                ])

                @foreach ($skillGroups as $group)
                    @if ($group['matched']->isNotEmpty() || $group['missing']->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $group['label'] }}</h3>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($group['matched'] as $skill)
                                    <li>
                                        <x-chip variant="matched">
                                            {{ $skill->name }}
                                            <span class="sr-only">{{ __('(you have this)') }}</span>
                                        </x-chip>
                                    </li>
                                @endforeach
                                @foreach ($group['missing'] as $skill)
                                    <li>
                                        <x-chip variant="missing">
                                            {{ $skill->name }}
                                            <span class="sr-only">{{ __('(missing from your profile)') }}</span>
                                        </x-chip>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                @if ($breakdown->score === null && $this->jobPosting->skills->isNotEmpty())
                    <p class="mt-3 text-sm text-ink-muted">
                        <a href="{{ route('candidate.skills.edit') }}" class="font-medium text-sunset-small hover:underline" wire:navigate>{{ __('Add your skills') }}</a>
                        {{ __('to get a match score.') }}
                    </p>
                @endif

                <dl class="mt-4 divide-y divide-line text-sm">
                    @foreach ($this->rows() as $row)
                        <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-[8rem_1fr_auto] sm:items-center sm:gap-3">
                            <dt class="font-medium text-ink">{{ __($row['check']->label()) }}</dt>
                            <dd class="text-ink-muted">
                                {{ __('This job: :job', ['job' => $row['job']]) }}
                                <span class="text-line-strong" aria-hidden="true">·</span>
                                {{ __('You: :you', ['you' => $row['you']]) }}
                            </dd>
                            <dd>
                                @switch($row['result']->value)
                                    @case('fits')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700 dark:text-success-300">
                                            <flux:icon.check-circle variant="micro" class="size-4" aria-hidden="true" />{{ __('Fits') }}
                                        </span>
                                        @break
                                    @case('misses')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-danger-700 dark:text-danger-300">
                                            <flux:icon.x-circle variant="micro" class="size-4" aria-hidden="true" />{{ __('Doesn\'t fit') }}
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-ink-muted">
                                            <flux:icon.minus-circle variant="micro" class="size-4" aria-hidden="true" />{{ __('Can\'t compare') }}
                                        </span>
                                @endswitch
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-3 text-xs text-ink-muted">
                    {{ __('Compared with your') }}
                    <a href="{{ route('candidate.preferences.edit') }}" class="font-medium text-sunset-small hover:underline" wire:navigate>{{ __('job preferences') }}</a>
                    {{ __('and') }}
                    <a href="{{ route('candidate.experience.index') }}" class="font-medium text-sunset-small hover:underline" wire:navigate>{{ __('work history') }}</a>.
                    {{ __('Employers see only the skills match.') }}
                </p>

                {{-- The AI explanation: offered, running, shown, failed, or out of allowance.
                     Plans without it see nothing here. --}}
                @php($availability = $this->aiAvailability)

                @if ($aiStatus === 'running')
                    <x-ai-working wire:poll.2s="checkAi" class="mt-4" :heading="__('Reading the job…')">{{ __('This usually takes a few seconds.') }}</x-ai-working>
                @elseif ($aiStatus === 'done' && $shown = $this->shownExplanation())
                    <x-card subtle padding="sm" class="mt-4 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium text-ink">{{ __('AI explanation') }}</h3>
                            <x-chip>{{ __('AI-generated — may be wrong') }}</x-chip>
                        </div>
                        @if ($shown->summary !== '')
                            <p class="mt-2 text-ink-soft">{{ $shown->summary }}</p>
                        @endif
                        @foreach ([
                            __('Where you fit') => $shown->strengths,
                            __('What the job asks that your profile doesn\'t show') => $shown->gaps,
                            __('Worth stressing when you apply') => $shown->tips,
                        ] as $title => $points)
                            @if ($points !== [])
                                <h4 class="mt-3 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $title }}</h4>
                                <ul class="mt-1 list-disc space-y-1 pl-5 text-ink-soft">
                                    @foreach ($points as $point)
                                        <li>{{ $point }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    </x-card>
                @elseif ($aiStatus === 'unavailable' || ($aiStatus === null && $availability === \App\Enums\AiAvailability::LimitReached))
                    <p class="mt-4 text-sm text-ink-muted">
                        {{ __("You've used this month's AI explanations. They reset on :date.", ['date' => \App\Support\LocalTime::of(now()->startOfMonth()->addMonth())->format(\App\Support\DateFormat::MOMENT)]) }}
                    </p>
                @elseif ($availability === \App\Enums\AiAvailability::Available && in_array($aiStatus, [null, 'failed'], true))
                    <x-card subtle padding="sm" class="mt-4 text-sm">
                        @if ($aiStatus === 'failed')
                            <p class="font-medium text-ink">{{ __("The AI couldn't explain this match right now.") }}</p>
                            <p class="mt-1 text-ink-muted">{{ __('You can try again; the match above does not depend on it.') }}</p>
                        @elseif (! $this->aiHasMaterial)
                            <p class="text-ink-muted">{{ __('Add your skills or work history, and the AI can explain how you fit this job.') }}</p>
                        @else
                            <p class="text-ink-muted">{{ __('The AI can explain in words where you fit this job, what is missing, and what to stress when you apply.') }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <flux:button wire:click="explain" icon="sparkles" size="sm" :disabled="! $this->aiHasMaterial">
                                {{ $aiStatus === 'failed' ? __('Try again') : __('Explain my match') }}
                            </flux:button>
                            <span class="text-xs text-ink-muted">
                                {{ __("Your profile (not your CV, name, contact details or salary) is sent to Anthropic. Anthropic doesn't train on it and, by default, deletes it within 30 days.") }}
                            </span>
                        </div>
                    </x-card>
                @endif
            @endif
        </x-card>
    @endif
</div>
