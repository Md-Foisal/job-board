<?php

use App\Actions\SaveJobPosting;
use App\Enums\EmploymentType;
use App\Enums\ModerationStatus;
use App\Enums\SalaryPeriod;
use App\Enums\SkillImportance;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Rules\CurrencyInUse;
use App\Support\ClosingDate;
use App\Support\LocalTime;
use App\Support\PublicCache;
use App\Support\SalaryCurrencies;
use App\Support\SubmissionLimits;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Job posting')] class extends Component {
    public Company $company;

    public ?JobPosting $jobPosting = null;

    public string $title = '';

    public string $description = '';

    public string $employmentType = 'full-time';

    public string $workplaceType = 'onsite';

    public ?string $locationCity = null;

    public ?string $locationCountry = null;

    public ?string $minExperienceYears = null;

    public ?string $salaryMin = null;

    public ?string $salaryMax = null;

    public ?string $salaryCurrency = null;

    public ?string $salaryPeriod = 'monthly';

    public bool $salaryNegotiable = false;

    public string $expiresAt = '';

    /** @var array<int, int> */
    public array $categories = [];

    /** @var array<int, string> skill id => importance */
    public array $skills = [];

    /** @var array<int, string> */
    public array $screeningQuestions = [];

    public string $skillSearch = '';

    public function mount(Company $company, ?JobPosting $jobPosting = null): void
    {
        $this->company = $company;

        if ($jobPosting === null) {
            $this->authorize('create', [JobPosting::class, $company]);
            $this->expiresAt = ClosingDate::monthAfter($company)->setTimezone($company->timezone)->toDateString();
            $this->salaryCurrency = $this->lastCurrencyUsed();

            return;
        }

        $this->authorize('update', $jobPosting);

        $this->jobPosting = $jobPosting;
        $this->title = $jobPosting->title;
        $this->description = (string) $jobPosting->description;
        $this->employmentType = $jobPosting->employment_type->value;
        $this->workplaceType = $jobPosting->workplace_type->value;
        $this->locationCity = $jobPosting->location_city;
        $this->locationCountry = $jobPosting->location_country;
        $this->minExperienceYears = (string) $jobPosting->min_experience_years;
        $this->salaryMin = (string) $jobPosting->salary_min;
        $this->salaryMax = (string) $jobPosting->salary_max;
        $this->salaryCurrency = $jobPosting->salary_currency;
        // The select has no empty option: holding nothing, it would show
        // "Hourly" while saving no period. A negotiable posting stores none,
        // so it reopens on monthly, the default for a new posting.
        $this->salaryPeriod = $jobPosting->salary_period?->value ?? SalaryPeriod::Monthly->value;
        $this->salaryNegotiable = $jobPosting->salary_negotiable;
        $this->expiresAt = ClosingDate::day($jobPosting)->toDateString();
        $this->categories = $jobPosting->categories->pluck('id')->all();
        $this->skills = $jobPosting->skills
            ->mapWithKeys(fn ($skill) => [$skill->id => $skill->pivot->importance->value])
            ->all();
        $this->screeningQuestions = $jobPosting->screeningQuestions->pluck('question_text')->all();
    }

    /**
     * Most companies pay in one currency, so a new posting starts with the
     * one their last posting used rather than making them find it again in
     * a list of 150. A company's first posting starts with none: the product
     * is used worldwide, so there is no fair default.
     */
    private function lastCurrencyUsed(): ?string
    {
        $code = $this->company->jobPostings()
            ->whereNotNull('salary_currency')
            ->latest('id')
            ->value('salary_currency');

        return SalaryCurrencies::isInUse($code) ? $code : null;
    }

    #[Computed]
    public function allCategories()
    {
        return PublicCache::lookupModels('categories', Category::class, fn () => Category::orderBy('name')->get());
    }

    #[Computed]
    public function chosenSkills()
    {
        return Skill::whereIn('id', array_keys($this->skills))->orderBy('name')->get();
    }

    #[Computed]
    public function skillMatches()
    {
        if (mb_strlen(trim($this->skillSearch)) < 2) {
            return collect();
        }

        return Skill::where('name', 'like', '%'.$this->skillSearch.'%')
            ->whereNotIn('id', array_keys($this->skills))
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    public function addSkill(int $skillId): void
    {
        $this->skills[$skillId] = SkillImportance::Required->value;
        $this->skillSearch = '';
        unset($this->chosenSkills, $this->skillMatches);
    }

    public function removeSkill(int $skillId): void
    {
        unset($this->skills[$skillId]);
        unset($this->chosenSkills, $this->skillMatches);
    }

    public function addQuestion(): void
    {
        $this->screeningQuestions[] = '';
    }

    public function removeQuestion(int $index): void
    {
        unset($this->screeningQuestions[$index]);
        $this->screeningQuestions = array_values($this->screeningQuestions);
    }

    /**
     * Without these, Laravel builds the message out of the property name
     * and tells the employer "the location country field is required",
     * which is the database talking, not the form they are looking at.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => __('job title'),
            'description' => __('description'),
            'employmentType' => __('employment type'),
            'workplaceType' => __('workplace'),
            'locationCountry' => __('country'),
            'locationCity' => __('city'),
            'minExperienceYears' => __('minimum experience'),
            'salaryMin' => __('salary from'),
            'salaryMax' => __('salary to'),
            'salaryCurrency' => __('currency'),
            'salaryPeriod' => __('salary period'),
            'expiresAt' => __('closing date'),
            'screeningQuestions.*' => __('screening question'),
        ];
    }

    /**
     * Two sets, not one. A draft is where an unfinished posting is parked --
     * the whole reason the state exists -- so demanding every published-post
     * field before it can be saved would make the button a lie. Everything
     * that is filled in is still shape-checked (lengths, integers, enums,
     * foreign keys), so a draft can never hold a value that would fail on
     * the way out; only the "you must decide this" rules wait for publish.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rulesFor(bool $publish): array
    {
        $required = fn (array $rules) => $publish
            ? ['required', ...$rules]
            : ['nullable', ...$rules];

        $negotiable = Rule::excludeIf($this->salaryNegotiable);
        $withPay = $publish ? ['required_with:salaryMin,salaryMax'] : [];

        return [
            // Even a draft needs this: it is how the posting is told apart
            // from the others in the list you come back to.
            'title' => ['required', 'string', 'max:255'],
            'description' => $required(['string', 'max:20000']),
            'employmentType' => $required([Rule::enum(EmploymentType::class)]),
            'workplaceType' => $required([Rule::enum(WorkplaceType::class)]),
            // A remote role still says where someone may work from, which is
            // the difference between an honest listing and the one that
            // reveals "US only" ten minutes into reading it.
            'locationCountry' => $required(['string', 'max:255']),
            'locationCity' => ['nullable', 'string', 'max:255'],
            'minExperienceYears' => ['nullable', 'integer', 'min:0', 'max:50'],
            // Negotiable hides the pay fields, so whatever they still hold is
            // left out entirely: never saved, and never an error on a field
            // the employer can no longer see.
            'salaryMin' => [$negotiable, 'nullable', 'integer', 'min:0'],
            'salaryMax' => [$negotiable, 'nullable', 'integer', 'min:0', 'gte:salaryMin'],
            // A figure without a currency means nothing to a candidate, and
            // without one the job cannot be compared with their preference.
            'salaryCurrency' => [$negotiable, ...$withPay, 'nullable', 'string', new CurrencyInUse],
            'salaryPeriod' => [$negotiable, ...$withPay, 'nullable', Rule::enum(SalaryPeriod::class)],
            'expiresAt' => $publish
                ? ['required', 'date', 'after:'.ClosingDate::today($this->company)]
                : ['nullable', 'date'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'skills' => ['array'],
            'skills.*' => [Rule::enum(SkillImportance::class)],
            'screeningQuestions' => ['array', 'max:10'],
            'screeningQuestions.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(SaveJobPosting $saveJobPosting, bool $publish = false): void
    {
        $this->jobPosting
            ? $this->authorize('update', $this->jobPosting)
            : $this->authorize('create', [JobPosting::class, $this->company]);

        // Only a new posting counts: editing one that already exists is
        // never limited.
        $limitKey = SubmissionLimits::jobPostingKey($this->company);
        $creating = $this->jobPosting === null;

        if ($creating && RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::JOB_POSTINGS_PER_DAY)) {
            $message = SubmissionLimits::jobPostingLimitMessage($this->company);

            Flux::toast(variant: 'warning', duration: 10000, heading: $message['heading'], text: $message['text']);

            return;
        }

        try {
            $validated = $this->validate($this->rulesFor($publish));
        } catch (ValidationException $e) {
            // The buttons are at the bottom of a form several screens tall,
            // so an error rendered next to a field two screens up is an
            // error nobody sees: pressing the button appears to do nothing.
            $this->dispatch('form-invalid');

            throw $e;
        }

        // Livewire sets properties straight from the browser, past the
        // middleware that turns an empty field into null, so a cleared
        // number arrives as "" -- which PostgreSQL refuses for an integer
        // column.
        $number = fn (mixed $value): ?int => blank($value) ? null : (int) $value;

        $jobPosting = $saveJobPosting(
            $this->company,
            auth()->user(),
            [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'employment_type' => $validated['employmentType'],
                'workplace_type' => $validated['workplaceType'],
                'location_city' => $validated['locationCity'] ?? null,
                'location_country' => $validated['locationCountry'],
                'min_experience_years' => $number($validated['minExperienceYears'] ?? null),
                'salary_min' => $number($validated['salaryMin'] ?? null),
                'salary_max' => $number($validated['salaryMax'] ?? null),
                'salary_currency' => blank($validated['salaryCurrency'] ?? null) ? null : $validated['salaryCurrency'],
                'salary_period' => $validated['salaryPeriod'] ?? null,
                'salary_negotiable' => $this->salaryNegotiable,
                // A draft nobody can see still needs a closing date in the
                // column, so an unfinished one gets the same month-out
                // default the form starts with. Publishing re-checks it.
                'expires_at' => filled($validated['expiresAt'])
                    ? ClosingDate::endOf($validated['expiresAt'], $this->company)
                    : ClosingDate::monthAfter($this->company),
                'categories' => $validated['categories'] ?? [],
                'skills' => $validated['skills'] ?? [],
                'screening_questions' => $validated['screeningQuestions'] ?? [],
                'publish' => $publish,
            ],
            $this->jobPosting,
        );

        if ($creating) {
            RateLimiter::hit($limitKey, 86400);
        }

        // Every other action in this shell says so when it worked --
        // closing a posting, moving a stage, inviting someone -- and this
        // is the largest of them; landing back on the list with a new row
        // and no word about it is the odd one out.
        // A company still earning trust waits for a reviewer; saying
        // "published" there would send them looking for a posting nobody
        // can see yet.
        session()->flash('success', match (true) {
            ! $publish => __('Draft saved.'),
            $jobPosting->moderation_status === ModerationStatus::Pending => __('Sent for review. It goes live once approved, usually within a day.'),
            default => __('Job posting published.'),
        });

        $this->redirectRoute('employer.jobs.index', $this->company, navigate: true);
    }

    public function saveAndPublish(SaveJobPosting $saveJobPosting): void
    {
        $this->save($saveJobPosting, publish: true);
    }
}; ?>

<x-page width="narrow">
    <x-page-header
        :title="$jobPosting ? __('Edit job posting') : __('Post a job')"
        :back="route('employer.jobs.index', $this->company)"
        :back-label="__('Job postings')"
    />

    {{-- novalidate: the browser's own bubble fires before Livewire ever
         runs, so it wins the race with an unstyled, untranslated message
         that points at a field the user cannot see, and it blocks the
         draft path for fields a draft is allowed to leave empty. The
         required attributes stay for the asterisk and for screen readers;
         what people read is the app's own inline error. --}}
    <form wire:submit="saveAndPublish" novalidate class="flex flex-col gap-8">
        <x-card>
            <flux:heading size="lg">{{ __('The role') }}</flux:heading>

            <div class="mt-6 flex flex-col gap-6">
                <flux:input wire:model="title" :label="__('Job title')" required />

                <x-rich-text-editor wire="description" :value="$description" :label="__('Description')" />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:select wire:model="employmentType" :label="__('Employment type')">
                        @foreach (EmploymentType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input type="number" wire:model="minExperienceYears" :label="__('Minimum experience (years)')" min="0" />
                </div>
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Where') }}</flux:heading>

            <div class="mt-6 flex flex-col gap-6">
                <flux:select wire:model="workplaceType" :label="__('Workplace')">
                    @foreach (WorkplaceType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Help text hangs below the inputs, not above them: a
                     leading description is part of the field box, so two
                     fields side by side whose descriptions are one line and
                     two lines long end up with their inputs on different
                     baselines. "(optional)" goes in the label itself rather
                     than in a badge -- a badge is taller than plain label
                     text and knocks the row out of line again, and the
                     GOV.UK pattern of naming it in the label is the one
                     that survives being read aloud. --}}
                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <flux:input wire:model="locationCity" :label="__('City (optional)')" />
                    <flux:input
                        wire:model="locationCountry"
                        :label="__('Country')"
                        required
                        description:trailing="{{ __('Where someone must be able to work from, remote roles included.') }}"
                    />
                </div>
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Pay') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Most candidates will not apply without it.') }}</flux:text>

            <div class="mt-6 flex flex-col gap-6">
                <flux:checkbox wire:model.live="salaryNegotiable" :label="__('Negotiable — no range given')" />

                @unless ($salaryNegotiable)
                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:input type="number" wire:model="salaryMin" :label="__('From')" min="0" />
                        <flux:input type="number" wire:model="salaryMax" :label="__('To')" min="0" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:select wire:model="salaryCurrency" :label="__('Currency')" :placeholder="__('Choose a currency')">
                            @foreach (SalaryCurrencies::options($salaryCurrency) as $code => $label)
                                <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="salaryPeriod" :label="__('Per')">
                            @foreach (SalaryPeriod::cases() as $period)
                                <flux:select.option value="{{ $period->value }}">{{ $period->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endunless
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Skills') }}</flux:heading>
            <flux:text class="mt-1">{{ __('These decide the match percentage candidates see.') }}</flux:text>

            <div class="mt-6 flex flex-col gap-4">
                @foreach ($this->chosenSkills as $skill)
                    <div wire:key="skill-{{ $skill->id }}" class="flex items-center gap-3">
                        <span class="flex-1 text-sm font-medium text-ink">{{ $skill->name }}</span>

                        <flux:select wire:model="skills.{{ $skill->id }}" size="sm" class="w-44" :aria-label="__('How important is :skill', ['skill' => $skill->name])">
                            @foreach (SkillImportance::cases() as $importance)
                                <flux:select.option value="{{ $importance->value }}">{{ $importance->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="removeSkill({{ $skill->id }})" :aria-label="__('Remove :skill', ['skill' => $skill->name])" />
                    </div>
                @endforeach

                <div>
                    <flux:input wire:model.live.debounce.300ms="skillSearch" :label="__('Add a skill')" :placeholder="__('Start typing...')" />

                    <flux:text size="sm" class="mt-2" wire:loading wire:target="skillSearch">
                        {{ __('Searching...') }}
                    </flux:text>

                    @if ($this->skillMatches->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->skillMatches as $skill)
                                <flux:button size="sm" variant="subtle" wire:key="match-{{ $skill->id }}" wire:click="addSkill({{ $skill->id }})">
                                    {{ $skill->name }}
                                </flux:button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Categories') }}</flux:heading>

            <div class="mt-6 flex flex-wrap gap-x-6 gap-y-3">
                @foreach ($this->allCategories as $category)
                    <flux:checkbox wire:model="categories" value="{{ $category->id }}" :label="$category->name" wire:key="category-{{ $category->id }}" />
                @endforeach
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Screening questions') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('The sharpest tool you have: the wrong candidates either do not apply, or rule themselves out in one line.') }}
            </flux:text>

            <div class="mt-6 flex flex-col gap-3">
                @foreach ($screeningQuestions as $index => $question)
                    <div wire:key="question-{{ $index }}" class="flex items-start gap-2">
                        <flux:input wire:model="screeningQuestions.{{ $index }}" class="flex-1" :aria-label="__('Question :number', ['number' => $index + 1])" />
                        <flux:button variant="subtle" icon="x-mark" wire:click="removeQuestion({{ $index }})" :aria-label="__('Remove question :number', ['number' => $index + 1])" />
                    </div>
                @endforeach

                <div>
                    <flux:button size="sm" variant="subtle" icon="plus" wire:click="addQuestion" type="button">
                        {{ __('Add a question') }}
                    </flux:button>
                </div>
            </div>
        </x-card>

        <x-card>
            <flux:heading size="lg">{{ __('Closing date') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Applications are taken until the end of this day in your company\'s time zone, :zone, and then the posting closes itself, so nobody applies to something already filled.', ['zone' => LocalTime::label($company->timezone)]) }}</flux:text>

            <div class="mt-6">
                <flux:input type="date" wire:model="expiresAt" :label="__('Accept applications until')" required />
            </div>
        </x-card>

        <div class="flex flex-wrap justify-end gap-2">
            <flux:button variant="ghost" type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                {{ __('Save as draft') }}
            </flux:button>
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveAndPublish">
                <span wire:loading.remove wire:target="saveAndPublish">{{ $jobPosting ? __('Save and publish') : __('Publish') }}</span>
                <span wire:loading wire:target="saveAndPublish">{{ __('Saving...') }}</span>
            </flux:button>
        </div>
    </form>
</x-page>
