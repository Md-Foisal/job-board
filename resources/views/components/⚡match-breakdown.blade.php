<?php

use App\Enums\MatchCheck;
use App\Enums\MatchCheckResult;
use App\Enums\SalaryPeriod;
use App\Models\CandidatePreference;
use App\Models\JobPosting;
use App\Services\MatchScoreCalculator;
use App\Support\MatchBreakdown;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The candidate's own view of how they fit one job, beside the Apply
 * button. It loads in its own request after the page, so the job page
 * itself stays the same for everyone and one candidate's details never
 * sit in a page another person could be served.
 */
new class extends Component
{
    public JobPosting $jobPosting;

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
                    : __('From :amount a month', ['amount' => $this->money($preference->desired_salary_currency, $preference->desired_salary_min)]),
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
            $min !== null && $max !== null => $this->money($job->salary_currency, $min).'–'.number_format($max),
            $min !== null => __('from :amount', ['amount' => $this->money($job->salary_currency, $min)]),
            default => __('up to :amount', ['amount' => $this->money($job->salary_currency, $max)]),
        };

        return $job->salary_period === SalaryPeriod::Monthly
            ? __(':amount a month', ['amount' => $amount])
            : __('About :amount a month', ['amount' => $amount]);
    }

    private function money(?string $currency, int $amount): string
    {
        return trim(strtoupper(trim((string) $currency)).' '.number_format($amount));
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
    <section class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" aria-busy="true">
        <span class="sr-only">{{ __('Loading how you match this job') }}</span>
        <div class="animate-pulse space-y-3" aria-hidden="true">
            <div class="h-5 w-40 rounded bg-zinc-200 dark:bg-zinc-800"></div>
            <div class="h-4 w-3/4 rounded bg-zinc-100 dark:bg-zinc-800/60"></div>
            <div class="h-4 w-2/3 rounded bg-zinc-100 dark:bg-zinc-800/60"></div>
            <div class="h-4 w-1/2 rounded bg-zinc-100 dark:bg-zinc-800/60"></div>
        </div>
    </section>
@endplaceholder

<div>
    @if ($this->breakdown)
        @php($breakdown = $this->breakdown)

        <section class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" aria-labelledby="match-breakdown-heading">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="match-breakdown-heading" class="font-display text-base font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ __('How you match') }}
                </h2>
                <x-match-score :score="$breakdown->score" size="md" />
                <span class="text-xs text-zinc-500 dark:text-zinc-500">{{ __('Only you can see this.') }}</span>
            </div>

            @if ($breakdown->isEmpty())
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
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
                            <h3 class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-500">{{ $group['label'] }}</h3>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($group['matched'] as $skill)
                                    <li class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-950 dark:text-success-300">
                                        <flux:icon.check variant="micro" class="size-3.5" aria-hidden="true" />
                                        {{ $skill->name }}
                                        <span class="sr-only">{{ __('(you have this)') }}</span>
                                    </li>
                                @endforeach
                                @foreach ($group['missing'] as $skill)
                                    <li class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        <flux:icon.x-mark variant="micro" class="size-3.5" aria-hidden="true" />
                                        {{ $skill->name }}
                                        <span class="sr-only">{{ __('(missing from your profile)') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                @if ($breakdown->score === null && $this->jobPosting->skills->isNotEmpty())
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                        <a href="{{ route('candidate.skills.edit') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400" wire:navigate>{{ __('Add your skills') }}</a>
                        {{ __('to get a match score.') }}
                    </p>
                @endif

                <dl class="mt-4 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    @foreach ($this->rows() as $row)
                        <div class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-[8rem_1fr_auto] sm:items-center sm:gap-3">
                            <dt class="font-medium text-zinc-800 dark:text-zinc-200">{{ __($row['check']->label()) }}</dt>
                            <dd class="text-zinc-600 dark:text-zinc-400">
                                {{ __('This job: :job', ['job' => $row['job']]) }}
                                <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">·</span>
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
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                            <flux:icon.minus-circle variant="micro" class="size-4" aria-hidden="true" />{{ __('Can\'t compare') }}
                                        </span>
                                @endswitch
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-500">
                    {{ __('Compared with your') }}
                    <a href="{{ route('candidate.preferences.edit') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400" wire:navigate>{{ __('job preferences') }}</a>
                    {{ __('and') }}
                    <a href="{{ route('candidate.experience.index') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400" wire:navigate>{{ __('work history') }}</a>.
                    {{ __('Employers see only the skills match.') }}
                </p>
            @endif
        </section>
    @endif
</div>
