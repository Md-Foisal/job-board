<?php

use App\Enums\AlertFrequency;
use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\JobAlert;
use App\Models\Skill;
use App\Support\JobSearchCriteria;
use App\Support\PublicCache;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Job alerts')] class extends Component {
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $frequency = 'daily';

    public string $q = '';

    public ?int $skill = null;

    public ?int $category = null;

    public string $location = '';

    public ?string $workplaceType = null;

    public ?string $employmentType = null;

    public ?int $salaryMin = null;

    public ?int $salaryMax = null;

    public ?int $experience = null;

    /**
     * "Create job alert" on the search page lands here with its filters in
     * the address, so the form opens already describing that search.
     */
    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->create();
            $this->fillCriteria(JobSearchCriteria::from(request()->query()));
            [$skillNames, $categoryNames] = JobSearchCriteria::names([$this->criteria()]);
            $this->name = Str::limit(implode(', ', JobSearchCriteria::describe($this->criteria(), $skillNames, $categoryNames)), 90);
        }
    }

    public function with(): array
    {
        $jobAlerts = auth()->user()->jobAlerts()->latest()->latest('id')->get();
        [$skillNames, $categoryNames] = JobSearchCriteria::names($jobAlerts->pluck('criteria'));

        return [
            'jobAlerts' => $jobAlerts,
            'descriptions' => $jobAlerts->mapWithKeys(fn (JobAlert $jobAlert) => [
                $jobAlert->id => implode(' · ', JobSearchCriteria::describe($jobAlert->criteria, $skillNames, $categoryNames)),
            ]),
            'skills' => PublicCache::lookup('skills', fn () => Skill::orderBy('name')->get()),
            'categories' => PublicCache::lookup('categories', fn () => Category::orderBy('name')->get()),
            'workplaceTypes' => WorkplaceType::cases(),
            'employmentTypes' => EmploymentType::cases(),
            'frequencies' => AlertFrequency::cases(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();

        if (auth()->user()->jobAlerts()->count() >= JobAlert::MAX_PER_CANDIDATE) {
            Flux::toast(variant: 'warning', text: __('You can keep up to :limit job alerts. Remove one you no longer need to add another.', [
                'limit' => JobAlert::MAX_PER_CANDIDATE,
            ]));

            return;
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $jobAlert = auth()->user()->jobAlerts()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $jobAlert->id;
        $this->name = $jobAlert->name;
        $this->frequency = $jobAlert->frequency->value;
        $this->fillCriteria($jobAlert->criteria);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'frequency' => ['required', Rule::enum(AlertFrequency::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'skill' => ['nullable', 'integer', Rule::exists('skills', 'id')->withoutTrashed()],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'location' => ['nullable', 'string', 'max:100'],
            'workplaceType' => ['nullable', Rule::enum(WorkplaceType::class)],
            'employmentType' => ['nullable', Rule::enum(EmploymentType::class)],
            'salaryMin' => ['nullable', 'integer', 'min:1'],
            'salaryMax' => ['nullable', 'integer', 'min:1', 'gte:salaryMin'],
            'experience' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], attributes: [
            'q' => __('keywords'),
            'salaryMin' => __('minimum pay'),
            'salaryMax' => __('maximum pay'),
            'experience' => __('years of experience'),
        ]);

        $data = [
            'name' => $this->name,
            'frequency' => $this->frequency,
            'criteria' => $this->criteria(),
        ];

        if ($this->editingId) {
            auth()->user()->jobAlerts()->findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: __('Job alert updated.'));
        } else {
            // Checked again here, not only when the form opened: two tabs
            // could each have opened it while one slot was left.
            if (auth()->user()->jobAlerts()->count() >= JobAlert::MAX_PER_CANDIDATE) {
                $this->addError('name', __('You can keep up to :limit job alerts.', ['limit' => JobAlert::MAX_PER_CANDIDATE]));

                return;
            }

            auth()->user()->jobAlerts()->create($data);
            Flux::toast(variant: 'success', text: __("Job alert saved. We'll email you when new jobs match."));
        }

        $this->closeModal();
    }

    public function toggle(int $id): void
    {
        $jobAlert = auth()->user()->jobAlerts()->findOrFail($id);
        $jobAlert->update(['is_active' => ! $jobAlert->is_active]);
    }

    public function delete(int $id): void
    {
        auth()->user()->jobAlerts()->findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function criteria(): array
    {
        return JobSearchCriteria::from([
            'q' => $this->q,
            'skill' => $this->skill,
            'category' => $this->category,
            'location' => $this->location,
            'workplaceType' => $this->workplaceType,
            'employmentType' => $this->employmentType,
            'salaryMin' => $this->salaryMin,
            'salaryMax' => $this->salaryMax,
            'experience' => $this->experience,
        ]);
    }

    private function fillCriteria(array $criteria): void
    {
        $this->q = $criteria['q'] ?? '';
        $this->skill = $criteria['skill'] ?? null;
        $this->category = $criteria['category'] ?? null;
        $this->location = $criteria['location'] ?? '';
        $this->workplaceType = $criteria['workplaceType'] ?? null;
        $this->employmentType = $criteria['employmentType'] ?? null;
        $this->salaryMin = $criteria['salaryMin'] ?? null;
        $this->salaryMax = $criteria['salaryMax'] ?? null;
        $this->experience = $criteria['experience'] ?? null;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'frequency', 'q', 'skill', 'category', 'location', 'workplaceType', 'employmentType', 'salaryMin', 'salaryMax', 'experience']);
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto max-w-2xl px-6 py-10">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Job alerts') }}</flux:heading>
            <flux:subheading>{{ __('Searches we check for you. When new jobs match, we email them to you.') }}</flux:subheading>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('New alert') }}</flux:button>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($jobAlerts as $jobAlert)
            <div wire:key="job-alert-{{ $jobAlert->id }}" @class([
                'flex items-start justify-between gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900',
                'opacity-60' => ! $jobAlert->is_active,
            ])>
                <div class="flex min-w-0 gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                        <flux:icon name="bell" variant="mini" />
                    </div>

                    <div class="min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $jobAlert->name }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $descriptions[$jobAlert->id] }}
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge size="sm">{{ $jobAlert->frequency->label() }}</flux:badge>
                            @unless ($jobAlert->is_active)
                                <flux:badge size="sm" color="zinc">{{ __('Paused') }}</flux:badge>
                            @endunless
                            <flux:link :href="route('jobs.index', $jobAlert->criteria)" wire:navigate class="text-sm">
                                {{ __('See matching jobs') }}
                            </flux:link>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <flux:button wire:click="toggle({{ $jobAlert->id }})" variant="ghost" size="sm"
                        :icon="$jobAlert->is_active ? 'pause' : 'play'"
                        :aria-label="$jobAlert->is_active ? __('Pause') : __('Resume')" />
                    <flux:button wire:click="edit({{ $jobAlert->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit')" />
                    <flux:button wire:click="delete({{ $jobAlert->id }})" wire:confirm="{{ __('Delete this job alert?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Delete')" />
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No job alerts yet. Search for jobs and choose "Create job alert", or add one here.') }}
                </p>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showModal" class="max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit job alert') : __('New job alert') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Laravel jobs in Dhaka')" />

            <flux:radio.group wire:model="frequency" variant="segmented" :label="__('Email me')">
                @foreach ($frequencies as $option)
                    <flux:radio value="{{ $option->value }}" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            <flux:separator :text="__('Jobs that match')" />

            <flux:input wire:model="q" :label="__('Keywords')" :placeholder="__('Job title...')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model="skill" :label="__('Skill')">
                    <flux:select.option value="">{{ __('Any skill') }}</flux:select.option>
                    @foreach ($skills as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="category" :label="__('Category')">
                    <flux:select.option value="">{{ __('Any category') }}</flux:select.option>
                    @foreach ($categories as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="workplaceType" :label="__('Workplace')">
                    <flux:select.option value="">{{ __('Any workplace type') }}</flux:select.option>
                    @foreach ($workplaceTypes as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="employmentType" :label="__('Employment')">
                    <flux:select.option value="">{{ __('Any employment type') }}</flux:select.option>
                    @foreach ($employmentTypes as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="location" :label="__('City')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model="salaryMin" type="number" :label="__('Min pay / month')" />
                <flux:input wire:model="salaryMax" type="number" :label="__('Max pay / month')" />
                <flux:input wire:model="experience" type="number" :label="__('Your years of experience')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
