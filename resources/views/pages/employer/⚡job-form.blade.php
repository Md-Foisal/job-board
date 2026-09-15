<?php

use App\Actions\SaveJobPosting;
use App\Enums\EmploymentType;
use App\Enums\SalaryPeriod;
use App\Enums\SkillImportance;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::employer')] class extends Component {
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

    public ?string $salaryCurrency = 'BDT';

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
            $this->expiresAt = now()->addMonth()->toDateString();

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
        $this->salaryPeriod = $jobPosting->salary_period?->value;
        $this->salaryNegotiable = $jobPosting->salary_negotiable;
        $this->expiresAt = $jobPosting->expires_at->toDateString();
        $this->categories = $jobPosting->categories->pluck('id')->all();
        $this->skills = $jobPosting->skills
            ->mapWithKeys(fn ($skill) => [$skill->id => $skill->pivot->importance->value])
            ->all();
        $this->screeningQuestions = $jobPosting->screeningQuestions->pluck('question_text')->all();
    }

    #[Computed]
    public function allCategories()
    {
        return Category::orderBy('name')->get();
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

    public function save(SaveJobPosting $saveJobPosting, bool $publish = false): void
    {
        $this->jobPosting
            ? $this->authorize('update', $this->jobPosting)
            : $this->authorize('create', [JobPosting::class, $this->company]);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'employmentType' => ['required', Rule::enum(EmploymentType::class)],
            'workplaceType' => ['required', Rule::enum(WorkplaceType::class)],
            // A remote role still says where someone may work from, which is
            // the difference between an honest listing and the one that
            // reveals "US only" ten minutes into reading it.
            'locationCountry' => ['required', 'string', 'max:255'],
            'locationCity' => ['nullable', 'string', 'max:255'],
            'minExperienceYears' => ['nullable', 'integer', 'min:0', 'max:50'],
            'salaryMin' => ['nullable', 'integer', 'min:0'],
            'salaryMax' => ['nullable', 'integer', 'min:0', 'gte:salaryMin'],
            'salaryCurrency' => ['nullable', 'string', 'size:3'],
            'salaryPeriod' => ['nullable', Rule::enum(SalaryPeriod::class)],
            'expiresAt' => ['required', 'date', 'after:today'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'skills' => ['array'],
            'skills.*' => [Rule::enum(SkillImportance::class)],
            'screeningQuestions' => ['array', 'max:10'],
            'screeningQuestions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $jobPosting = $saveJobPosting(
            $this->company,
            auth()->user(),
            [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'employment_type' => $validated['employmentType'],
                'workplace_type' => $validated['workplaceType'],
                'location_city' => $validated['locationCity'] ?? null,
                'location_country' => $validated['locationCountry'],
                'min_experience_years' => $validated['minExperienceYears'] ?? null,
                'salary_min' => $this->salaryNegotiable ? null : ($validated['salaryMin'] ?? null),
                'salary_max' => $this->salaryNegotiable ? null : ($validated['salaryMax'] ?? null),
                'salary_currency' => $validated['salaryCurrency'] ?? null,
                'salary_period' => $validated['salaryPeriod'] ?? null,
                'salary_negotiable' => $this->salaryNegotiable,
                'expires_at' => $validated['expiresAt'],
                'categories' => $validated['categories'] ?? [],
                'skills' => $validated['skills'] ?? [],
                'screening_questions' => $validated['screeningQuestions'] ?? [],
                'publish' => $publish,
            ],
            $this->jobPosting,
        );

        $this->redirectRoute('employer.jobs.index', $this->company, navigate: true);
    }

    public function saveAndPublish(SaveJobPosting $saveJobPosting): void
    {
        $this->save($saveJobPosting, publish: true);
    }
}; ?>

<div class="mx-auto flex max-w-3xl flex-col gap-8">
    <div>
        <flux:heading size="xl" class="font-display">
            {{ $jobPosting ? __('Edit job posting') : __('Post a job') }}
        </flux:heading>
        <flux:text class="mt-1">
            {{ __('The clearer this is, the fewer wrong applications you have to read.') }}
        </flux:text>
    </div>

    <form wire:submit="saveAndPublish" class="flex flex-col gap-8">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
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
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Where') }}</flux:heading>

            <div class="mt-6 flex flex-col gap-6">
                <flux:select wire:model="workplaceType" :label="__('Workplace')">
                    @foreach (WorkplaceType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="locationCity" :label="__('City')" :description="__('Optional')" />
                    <flux:input
                        wire:model="locationCountry"
                        :label="__('Country')"
                        required
                        :description="__('Where someone must be able to work from, remote roles included.')"
                    />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Pay') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Most candidates will not apply without it.') }}</flux:text>

            <div class="mt-6 flex flex-col gap-6">
                <flux:checkbox wire:model.live="salaryNegotiable" :label="__('Negotiable -- no range given')" />

                @unless ($salaryNegotiable)
                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:input type="number" wire:model="salaryMin" :label="__('From')" min="0" />
                        <flux:input type="number" wire:model="salaryMax" :label="__('To')" min="0" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:input wire:model="salaryCurrency" :label="__('Currency')" maxlength="3" placeholder="BDT" />

                        <flux:select wire:model="salaryPeriod" :label="__('Per')">
                            @foreach (SalaryPeriod::cases() as $period)
                                <flux:select.option value="{{ $period->value }}">{{ $period->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endunless
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Skills') }}</flux:heading>
            <flux:text class="mt-1">{{ __('These decide the match percentage candidates see.') }}</flux:text>

            <div class="mt-6 flex flex-col gap-4">
                @foreach ($this->chosenSkills as $skill)
                    <div wire:key="skill-{{ $skill->id }}" class="flex items-center gap-3">
                        <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $skill->name }}</span>

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
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Categories') }}</flux:heading>

            <div class="mt-6 flex flex-wrap gap-x-6 gap-y-3">
                @foreach ($this->allCategories as $category)
                    <flux:checkbox wire:model="categories" value="{{ $category->id }}" :label="$category->name" wire:key="category-{{ $category->id }}" />
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
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
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Closing date') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The posting closes itself on this date, so nobody applies to something already filled.') }}</flux:text>

            <div class="mt-6">
                <flux:input type="date" wire:model="expiresAt" :label="__('Accept applications until')" required />
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <flux:button variant="ghost" type="button" wire:click="save">{{ __('Save as draft') }}</flux:button>
            <flux:button variant="primary" type="submit">
                {{ $jobPosting ? __('Save and publish') : __('Publish') }}
            </flux:button>
        </div>
    </form>
</div>
